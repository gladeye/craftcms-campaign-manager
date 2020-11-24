<?php
namespace Gladeye\CampaignManager\services;

use Craft;
use craft\db\Query;
use craft\elements\Entry;
use craft\helpers\ArrayHelper;
use Gladeye\CampaignManager\elements\Campaign;
use yii\base\Component;
use yii\web\BadRequestHttpException;

class Campaigns extends Component {

    public function deleteCampaignById() {

    }

    public function getCampaignForDraft($entry) {
        $draft = (new Query())
            ->select(['campaignId'])
            ->from('{{%campaigns_entries}}')
            ->where(['entryId' => $entry->id, 'type' => 'draft'])
            ->one();
        if($draft) {
            return Campaign::findOne(['id' => $draft['campaignId']]);
        } else {
            return null;
        }
    }
    //Need to find all campaigns that have a draft that matches the drafts of the entry
    //Also need to know rhe drafts for each campaign
    public function getEntryCampaigns($entryId) {
        $entry = Entry::findOne(['id' => $entryId]);
        if($entry) {
            $drafts = Craft::$app->getDrafts()->getEditableDrafts($entry);
            $draftsIndex = ArrayHelper::index($drafts, 'id');
            $draftIds = array_keys($draftsIndex);
            $campaignDrafts = (new Query())
                ->select(['campaignId', 'entryId'])
                ->from('{{%campaigns_entries}}')
                ->where(['entryId' => $draftIds, 'type' => 'draft'])
                ->all();
            $campaignDraftsIndex = ArrayHelper::index($campaignDrafts, 'campaignId');

            $campaignIds = array_keys($campaignDraftsIndex);

            $campaigns = Campaign::findAll(['id' => $campaignIds]);

            $output = [];

            foreach ($campaigns as $campaign) {
                $draft = $draftsIndex[$campaignDraftsIndex[$campaign->id]['entryId']];
                $output[$draft->draftId] = ['campaign' => $campaign, 'draft' => $draft, 'draftName' => $draft->getDraftName()];
            }

            return $output;
        }
        return [];
    }

    public function getEntriesForToken(string $token) : array {

    }

    public function getCampaignDrafts($campaign) {
        $draftIds = (new Query())
            ->select(['entryId'])
            ->from('{{%campaigns_entries}}')
            ->where(['campaignId' => $campaign->id, 'type' => 'draft'])
            ->column();

        return Entry::find()
            ->anyStatus()
            ->drafts(true)
            ->id($draftIds)
            ->all();
    }

}