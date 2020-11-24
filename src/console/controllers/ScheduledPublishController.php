<?php
namespace Gladeye\CampaignManager\console\controllers;

use craft\console\Controller;
use Gladeye\CampaignManager\elements\Campaign;

class ScheduledPublishController extends Controller {
    public function actionIndex() {
        /** @var Campaign[] $campaigns */
        $campaigns = Campaign::find()->publishDue(true)->all();
        foreach ($campaigns as $campaign) {
            $campaign->state = Campaign::STATUS_PUBLISHED;
            \Craft::$app->getElements()->saveElement($campaign);
        }
        foreach($campaigns as $campaign) {
            $campaign->publishDrafts();
        }
    }
}