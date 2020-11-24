<?php

namespace Gladeye\CampaignManager\elements;

use Craft;
use craft\base\Element;
use craft\db\Query;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\events\RevisionEvent;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\models\Section;
use craft\services\Revisions;
use craft\web\ErrorHandler;
use Gladeye\CampaignManager\elements\db\CampaignQuery;
use Gladeye\CampaignManager\Plugin;
use Gladeye\CampaignManager\records\Campaign as CampaignRecord;
use yii\base\Event;

class Campaign extends Element {
    // Static
    // =========================================================================
    const STATUS_PUBLISHED = 'published';
    const STATUS_PENDING = 'pending';
    const STATUS_DRAFT = 'draft';

    //Events
    const EVENT_AFTER_PUBLISH = 'afterPublish';
    const EVENT_BEFORE_PUBLISH = 'beforePublish';

    public static $entriesPublishedThisSession = [];
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('campaign-manager', 'Campaign');
    }
    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('campaign-manager', 'Campaigns');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle()
    {
        return 'campaign';
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     *
     * @return CampaignQuery The newly created [[CampaignQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new CampaignQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    public function getIsEditable(): bool
    {
        return true;
    }

    /**
     * Returns the name.
     *
     * @return string
     */
    public function getName(): string
    {
        return (string)$this->name;
    }

    public function __toString()
    {
        try {
            return $this->getName();
        } catch (\Throwable $e) {
            ErrorHandler::convertExceptionToError($e);
        }
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl()
    {
        if(Craft::$app->user->checkPermission('editCampaign')) {
            return UrlHelper::cpUrl('campaigns/' . $this->id . '?siteId=' . $this->siteId);
        } else {
            return UrlHelper::cpUrl('campaigns/' . $this->id . '/drafts?siteId=' . $this->siteId);
        }
    }


    protected static function defineTableAttributes(): array
    {
        return [
            'name' => ['label' => Craft::t('app', 'Title')],
            'description' => ['label' => Craft::t('campaign-manager', 'Description')],
            'publishedAt' => ['label' => Craft::t('campaign-manager', 'Publish Date')],
            'link' => ['label' => Craft::t('campaign-manager', 'View')],
        ];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'link':
                $suffix = $this->isDraft() ? '/drafts' : '/content';
                $link = UrlHelper::cpUrl('campaigns/' . $this->id . $suffix );
                return '<a href="' . $link . '">View Campaign</a>';
        }
        return parent::tableAttributeHtml($attribute);
    }

    public function getUiLabel(): string
    {
        return (string)$this->name;
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = ['name', 'description', 'publishedAt', 'link'];

        return $attributes;
    }

    protected static function defineActions(string $source = null): array
    {
        //Add edit / delete here


        $actions[] = Delete::class;

        $actions[] = [
            'type' => Delete::class,
            'withDescendants' => false,
        ];

        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [];
        error_log('context' . $context);
        $sources[] = [
            'key' => '*',
            'label' => Craft::t('campaign-manager', 'Current campaigns'),
            'criteria' => ['state' => self::STATUS_DRAFT]
        ];

        if ($context === 'index') {
            $sources[] = [
                'key' => 'published',
                'label' => Craft::t('campaign-manager', 'Published campaigns'),
                'criteria' => ['state' => self::STATUS_PUBLISHED]
            ];
            $sources[] = [
                'key' => 'pending',
                'label' => Craft::t('campaign-manager', 'Pending campaigns'),
                'criteria' => ['state' => self::STATUS_PENDING]
            ];

        }
        return $sources;


    }


    public function getEditorHtml(): string
    {
        $html = '';

        $html = Craft::$app->getView()->renderTemplate('campaign-manager/_campaignFields.twig', [
            'campaign' => $this,
        ]);
        // Render the custom fields
        $html .= parent::getEditorHtml();

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'publishedAt';
        return $attributes;
    }


    public function afterSave(bool $isNew)
    {
        if ($isNew) {
            $record = new CampaignRecord();
            $record->id = $this->id;
        } else {
            $record = CampaignRecord::findOne($this->id);
        }
        $record->name = $this->name;
        $record->description = $this->description;
        $record->publishedAt = $this->publishedAt;
        $record->state = $this->state;


        $dirtyAttributes = array_keys($record->getDirtyAttributes());

        $record->save(false);

        $this->setDirtyAttributes($dirtyAttributes);

        parent::afterSave($isNew);
    }

    public function publishDrafts($mergeDrafts = false) {
        $entryIds = (new Query())->select(['entryId'])
            ->from('{{%campaigns_entries}}')
            ->where(['campaignId' => $this->id, 'type' => 'draft'])
            ->column();
        $drafts = Entry::find()
            ->anyStatus()
            ->drafts(true)
            ->id($entryIds)
            ->all();

        foreach($drafts as $draft) {
            self::$entriesPublishedThisSession[] = $draft->getSourceId();
            $revision = Entry::find()->revisionOf($draft->getSourceId())->orderBy(['dateCreated' => SORT_DESC])->one();
            if($mergeDrafts) {
                Craft::$app->getDrafts()->mergeSourceChanges($draft);
            }
            $newEntry = Craft::$app->getDrafts()->applyDraft($draft);
            $success = Craft::$app->db->createCommand()
                ->insert('{{%campaigns_entries}}', [
                    'entryId' => $revision->id,
                    'campaignId' => $this->id,
                    'type' => 'revision'
                ])
                ->execute();
            $success = Craft::$app->db->createCommand()
                ->insert('{{%campaigns_entries}}', [
                    'entryId' => $newEntry->id,
                    'campaignId' => $this->id,
                    'type' => 'entry'
                ])
                ->execute();

        }
        $this->trigger(self::EVENT_AFTER_PUBLISH);
    }

    public function publish($futureDate = null, $mergeDrafts = false) {
        $this->trigger(self::EVENT_BEFORE_PUBLISH);
        $currentTimestamp = DateTimeHelper::currentTimeStamp();
        if($futureDate) {
            $this->publishedAt = $futureDate;
        } else if($this->publishedAt === null) {
            $this->publishedAt = new \DateTime();
        }

        if($this->publishedAt && $this->publishedAt->getTimestamp() > $currentTimestamp) {
            $this->state = self::STATUS_PENDING;
        } else {
            $this->state = self::STATUS_PUBLISHED;
        }
        if($this->state === self::STATUS_PUBLISHED) {
            $this->publishDrafts($mergeDrafts);
        }


        return Craft::$app->getElements()->saveElement($this);

    }



    public function getDrafts() {
        $draftsInCampaign = (new Query())
            ->select(['id', 'entryId'])
            ->from('{{%campaigns_entries}}')
            ->where(['campaignId' => $this->id, 'type' => 'draft'])
            ->all();
        $draftIds = array_map(function($draft) {
            return intval($draft['entryId']);
        }, $draftsInCampaign);
        $entries = Entry::findAll(['id' => $draftIds, 'drafts' => true, 'status' => null, 'enabledForSite' => false]);
        $output = [];
        foreach ($entries as $key => $entry) {
            $output[] = ['entry' => $entry, 'draft' => $draftsInCampaign[$key]];
        }
        return $output;
    }
    public function getPublishedContent() {
        $entriesInCampaign = (new Query())
            ->select(['id', 'entryId'])
            ->from('{{%campaigns_entries}}')
            ->where(['campaignId' => $this->id, 'type' => 'entry'])
            ->all();
        $entryIds = array_map(function($draft) {
            return intval($draft['entryId']);
        }, $entriesInCampaign);
        $revisions = Entry::find()->id($entryIds)->anyStatus()->revisions(true)->all();
        $entries = Entry::find()->id($entryIds)->anyStatus()->all();
        return array_merge($revisions, $entries);
    }

    public function isPublished() {
        return $this->state === self::STATUS_PUBLISHED;
    }
    public function isPending() {
        return $this->state === self::STATUS_PENDING;
    }
    public function isDraft() {
        return $this->state === self::STATUS_DRAFT;
    }


    public $name;

    public $description;

    public $publishedAt = null;

    public $state = 'draft';


}