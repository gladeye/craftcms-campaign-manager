<?php
namespace Gladeye\CampaignManager\controllers;

use Craft;
use craft\db\Query;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\records\Element;
use craft\web\Controller;
use Gladeye\CampaignManager\elements\Campaign;
use Gladeye\CampaignManager\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

class CampaignsController extends Controller {
    public function actionIndex() {
        $this->requirePermission('viewCampaign');
        return $this->renderTemplate('campaign-manager/campaigns');
    }

    public function actionViewDrafts(int $campaignId) : Response {
        $this->requirePermission('viewCampaign');
        $campaign = Campaign::findOne(['id' => $campaignId]);
        $drafts = $campaign->getDrafts();
        $variables = [
            'drafts' => $drafts,
            'campaign' => $campaign
        ];
        return $this->renderTemplate('campaign-manager/view', $variables);
    }
    public function actionViewContent(int $campaignId) : Response {
        $this->requirePermission('viewCampaign');
        $campaign = Campaign::findOne(['id' => $campaignId]);
        $entries = $campaign->getPublishedContent();
        $variables = [
            'entries' => $entries,
            'campaign' => $campaign
        ];
        return $this->renderTemplate('campaign-manager/content', $variables);
    }

    public function actionEditCampaign(int $campaignId = null, Campaign $campaign = null) : Response {
        if($campaignId !== null) {
            $this->requirePermission('editCampaign');
        } else {
            $this->requirePermission('createCampaign');
        }

        $variables = [];
        if($campaignId !== null) {
            $campaign = Campaign::findOne(['id' => $campaignId]);
        } else {
            $campaign = new Campaign;
        }

        $variables['campaign'] = $campaign;
        return $this->renderTemplate('campaign-manager/edit', $variables);
    }

    public function actionSaveCampaign() {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();



        $campaign = new Campaign();
        $campaign->id = $request->getBodyParam('campaignId');
        $campaign->uid = $request->getBodyParam('uid');
        $campaign->name = $request->getBodyParam('name');
        $campaign->description = $request->getBodyParam('description');
        $campaign->publishedAt = $request->getBodyParam('publishedAt') ? DateTimeHelper::toDateTime($request->getBodyParam('publishedAt')) : null;

        $elementRecord = Element::findOne($campaign->id);

        if($elementRecord) {
            $this->requirePermission('editCampaign');
        } else {
            $this->requirePermission('createCampaign');
        }

        if($elementRecord) {
            $campaign->uid = $elementRecord->uid;
        }

        $res = Craft::$app->getElements()->saveElement($campaign, true, false);


        if (!$res) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false
                ]);
            }
            // else, normal result
            Craft::$app->getSession()->setError(Craft::t('campaign-manager', 'Couldn’t save the campaign.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'campaign' => $campaign
            ]);

            return null;
        } else {

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'id' => $campaign->id
                ]);
            }
            // else, normal result
            Craft::$app->getSession()->setNotice(Craft::t('campaign-manager', 'Campaign saved.'));
            // return $this->redirectToPostedUrl($category);

            $url = $request->getBodyParam('redirectUrl');
            return $this->redirect($url);
        }
    }
    public function actionAddToCampaign() {
        $this->requirePostRequest();
        $this->requirePermission('addDraftToCampaign');
        $campaignId = $this->request->getRequiredBodyParam('campaignId');
        $draftId = $this->request->getRequiredBodyParam('draftId');
        $draft = Entry::find()
            ->drafts(true)
            ->anyStatus()
            ->where(['draftId' => $draftId])
            ->one();
        $campaign = Campaign::findOne(['id' => $campaignId]);
        $inCampaign = (new Query())
            ->from('{{%campaigns_entries}}')
            ->where(['entryId' => $draft->id, 'type' => 'draft'])
            ->exists();
        if(!$inCampaign) {

            $success = Craft::$app->db->createCommand()
                ->insert('{{%campaigns_entries}}', [
                    'entryId' => $draft->id,
                    'campaignId' => $campaign->id,
                    'type' => 'draft'
                ])
                ->execute();
        } else {
            $success = false;
        }
        if($success) {
            if($this->request->getAcceptsJson()) {
                return $this->asJson(['success' => true, 'campaign' => $campaign]);
            } else {
                return $this->asJson(['success' => false]);
            }
        }
    }

    public function actionPublishCampaign() {
        $this->requirePostRequest();
        $this->requirePermission('publishCampaign');
        $campaignId = $this->request->getRequiredBodyParam('campaignId');

        $campaign = Campaign::findOne(['id' => $campaignId]);

        if($campaign) {
            if($campaign->publish()) {
                $this->setSuccessFlash(Craft::t('campaign-manager', 'Campaign ' . $campaign->name . ' published.'));
            } else {
                $this->setFailFlash(Craft::t('campaign-manager', 'Campaign ' . $campaign->name . ' failed to publish.'));
            }

        }
        return $this->redirect('campaigns');
    }

    public function actionPublishAndMerge() {
        $this->requirePostRequest();
        $this->requirePermission('publishCampaign');
        $campaignId = $this->request->getRequiredBodyParam('campaignId');

        $campaign = Campaign::findOne(['id' => $campaignId]);

        if($campaign) {
            if($campaign->publish(null, true)) {
                $this->setSuccessFlash(Craft::t('campaign-manager', 'Campaign ' . $campaign->name . ' published.'));
            } else {
                $this->setFailFlash(Craft::t('campaign-manager', 'Campaign ' . $campaign->name . ' failed to publish.'));
            }

        }
        return $this->redirect('campaigns');
    }

    public function actionRemoveDraftById() {

        $this->requirePostRequest();
        $this->requirePermission('addDraftToCampaign');
        $id = $this->request->getRequiredBodyParam('id');



        $success = Craft::$app->db->createCommand()
            ->delete('{{%campaigns_entries}}', [
                'id' => $id,
            ])
            ->execute();

        if($success) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson(['success' => true]);
            }
        }

    }

    public function actionRemoveDraft() {

        $this->requirePostRequest();
        $this->requirePermission('addDraftToCampaign');
        $draftId = $this->request->getRequiredBodyParam('draftId');
        $campaignId = $this->request->getRequiredBodyParam('campaignId');

        $draft = Entry::find()
            ->drafts(true)
            ->anyStatus()
            ->where(['draftId' => $draftId])
            ->one();

        $success = Craft::$app->db->createCommand()
            ->delete('{{%campaigns_entries}}', [
                'entryId' => $draft->id,
                'campaignId' => $campaignId
            ])
            ->execute();

        if($success) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson(['success' => true]);
            }
        }

    }

    public function actionDeleteCampaign() {
        $this->requirePostRequest();
        $this->requirePermission('deleteCampaign');
        $entryId = $this->request->getRequiredBodyParam('campaignId');
        $siteId = $this->request->getBodyParam('siteId');
        $campaign = Craft::$app->getElements()->getElementById($entryId, Campaign::class, $siteId);

        if (!$campaign) {
            throw new BadRequestHttpException("Invalid campaign ID: $entryId");
        }

        $currentUser = Craft::$app->getUser()->getIdentity();


        if (!Craft::$app->getElements()->deleteElement($campaign)) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson(['success' => false]);
            }

            $this->setFailFlash(Craft::t('app', 'Couldn’t delete campaign.'));

            // Send the entry back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'campaign' => $campaign
            ]);

            return null;
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }

        $this->setSuccessFlash(Craft::t('app', 'Campaign deleted.'));
        return $this->redirectToPostedUrl($campaign);
    }



    public function actionCreatePreviewToken() : Response {
        $this->requirePostRequest();
        $this->requirePermission('viewCampaign');
        $campaignId = $this->request->getRequiredBodyParam('campaignId');
        $token = Plugin::$plugin->getTokens()->createToken($campaignId);

        if (!$token) {
            throw new ServerErrorHttpException(Craft::t('app', 'Could not create a preview token.'));
        }

        return $this->asJson(compact('token'));
    }

    public function actionGeneratePreviewLink(int $campaignId) : Response {
        $this->requirePermission('viewCampaign');
        $draftId = $this->request->getQueryParam('draftId');
        $campaign = Campaign::findOne($campaignId);
        if($draftId) {
            $draft = Entry::find()
                ->draftId($draftId)
                ->one();
            $baseUrl = $draft->url;

        } else {
            $baseUrl = UrlHelper::baseSiteUrl();
        }
        $previewToken = Plugin::$plugin->getTokens()->createToken($campaign->id);

        $redirectUrl =  UrlHelper::url($baseUrl, ['campaign-token' => $previewToken]);

        return $this->redirect($redirectUrl);
    }

    public function actionTest() {
        error_log('webhook triggered');
        return '';
    }
}