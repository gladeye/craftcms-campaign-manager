<?php
namespace Gladeye\CampaignManager;

use Craft;
use craft\controllers\GraphqlController;
use craft\db\Query;
use craft\elements\Entry;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\RevisionEvent;
use craft\services\Elements;
use craft\services\Revisions;
use craft\services\UserPermissions;
use craft\web\Application;
use craft\web\Controller;
use craft\web\twig\variables\Cp;
use craft\web\UrlManager;
use craft\webhooks\Plugin as WebhookPlugin;
use Gladeye\CampaignManager\elements\Campaign;
use Gladeye\CampaignManager\filters\CampaignFilter;
use Gladeye\CampaignManager\models\Settings;
use Gladeye\CampaignManager\services\Campaigns;
use Gladeye\CampaignManager\services\Tokens;
use yii\base\ActionEvent;
use yii\base\Event;

class Plugin extends \craft\base\Plugin
{
    public $hasCpSection = true;
    public $hasCpSettings = true;
    /**
     * @var Tokens
     */
    public $_tokensService;

    /**
     * @var Campaigns
     */
    public $_campaignsService;


    /**
     * @var Plugin
     */
    public static $plugin;
    /**
     * Returns the Tokens service.
     *
     * @return Tokens The Tokens service
     */
    public function getTokens()
    {
        if ($this->_tokensService == null) {
            $this->_tokensService = new Tokens();
        }
        /** @var Tokens */
        return $this->_tokensService;
    }
    public function getCampaigns()
    {
        if ($this->_campaignsService == null) {
            $this->_campaignsService = new Campaigns();
        }
        /** @var Campaigns */
        return $this->_campaignsService;
    }

    public function init()
    {
        parent::init();

        self::$plugin = $this;
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['campaigns'] = 'campaign-manager/campaigns/index';
                $event->rules['campaigns/new'] = 'campaign-manager/campaigns/edit-campaign';
                $event->rules['campaigns/<campaignId:\d+>'] = 'campaign-manager/campaigns/edit-campaign';
                $event->rules['campaigns/test'] = 'campaign-manager/campaigns/test';
                $event->rules['campaigns/<campaignId:\d+>/drafts'] = 'campaign-manager/campaigns/view-drafts';
                $event->rules['campaigns/<campaignId:\d+>/content'] = 'campaign-manager/campaigns/view-content';
                $event->rules['campaigns/<campaignId:\d+>/preview'] = 'campaign-manager/campaigns/generate-preview-link';
            }
        );
        Event::on(Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = Campaign::class;
            }
        );

        Event::on(Application::class, Application::EVENT_BEFORE_REQUEST, function(Event $e) {
            // @var craft\elements\Entry $entry
            $this->getTokens()->deleteExpiredTokens();
            $token = $e->sender->request->get('campaign-token');
            if($token) {
                $campaign = $this->getTokens()->getCampaignForToken($token);
                if($campaign) {
                    $drafts = $this->getCampaigns()->getCampaignDrafts($campaign);
                    foreach ($drafts as $draft) {
                        $draft->previewing = true;
                        Craft::$app->getElements()->setPlaceholderElement($draft);
                    }
                }
            }
        });
        Event::on(Revisions::class, Revisions::EVENT_AFTER_CREATE_REVISION, function(RevisionEvent $event) {
            $revision = Entry::find()->revisionOf($event->source->id)->orderBy(['dateCreated' => SORT_DESC])->offset(1)->one();
            if($revision) {
                $success = Craft::$app->db->createCommand()
                    ->update('{{%campaigns_entries}}', [
                        'entryId' => $revision->id
                    ], ['entryId' => $event->source->id, 'type' => 'entry'])
                    ->execute();
            }
        });

        if(class_exists(WebhookPlugin::class)) {

            Event::on(WebhookPlugin::class, WebhookPlugin::EVENT_REGISTER_FILTER_TYPES, function(craft\events\RegisterComponentTypesEvent $e) {
                $e->types[] = CampaignFilter::class;
            });
        }



        Craft::$app->view->hook('cp.entries.edit.details', function(array &$context) {
            if ($context['entry'] === null) {
                return '';
            }

            $context['campaigns'] = $this->getCampaigns()->getEntryCampaigns($context['entry']->sourceId ?: $context['entry']->id);
            $context['campaign'] = $this->getCampaigns()->getCampaignForDraft($context['entry']);
            return Craft::$app->getView()->renderTemplate('campaign-manager/_addCampaignSelector', $context);
        });

        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions['Campaigns'] = [
                    'editCampaign' => [
                        'label' => 'Edit Campaigns',
                        'nested' => [
                            'createCampaign' => [
                                'label' => 'Create Campaigns',
                            ],
                            'deleteCampaign' => [
                                'label' => 'Delete Campaigns',
                            ],
                            'publishCampaign' => [
                                'label' => 'Publish Campaigns'
                            ]
                        ]
                    ],
                    'viewCampaign' => [
                        'label' => 'View Campaigns'
                    ],
                    'addDraftToCampaign' => [
                        'label' => 'Add Draft to Campaign'
                    ],

                ];
            }
        );
    }

    public function getCpNavItem()
    {
        return [
            'url' => 'campaigns',
            'label' => Craft::t('campaign-manager', 'Campaigns'),
            'fontIcon' => 'share'
        ];
    }

    protected function createSettingsModel()
    {
        return new Settings();
    }
}
