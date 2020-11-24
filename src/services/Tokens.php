<?php
namespace Gladeye\CampaignManager\services;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use DateTime;
use Gladeye\CampaignManager\elements\Campaign;
use Gladeye\CampaignManager\records\CampaignToken;
use yii\base\Component;

class Tokens extends Component {
    private $_deletedExpiredTokens;
    public function getCampaignForToken($token) {
        $result = (new Query())
            ->select(['id', 'campaignId'])
            ->from(['{{%campaign_tokens}}'])
            ->where(['token' => $token])
            ->one();
        if(!$result) {
            return null;
        }
        return Campaign::findOne($result['campaignId']);
    }
    public function createToken($campaignId, DateTime $expiryDate = null) {

        if (!$expiryDate) {
            $generalConfig = Craft::$app->getConfig()->getGeneral();
            $interval = DateTimeHelper::secondsToInterval($generalConfig->defaultTokenDuration);
            $expiryDate = DateTimeHelper::currentUTCDateTime();
            $expiryDate->add($interval);
        }

        $tokenRecord = new CampaignToken();
        $tokenRecord->token = Craft::$app->getSecurity()->generateRandomString(32);
        $tokenRecord->campaignId = $campaignId;

        $tokenRecord->expiryDate = $expiryDate;

        $success = $tokenRecord->save();
        error_log(print_r($tokenRecord->errors, true));
        if ($success) {
            return $tokenRecord->token;
        }

        return false;
    }

    /**
     * Deletes any expired tokens.
     *
     * @return bool
     */
    public function deleteExpiredTokens(): bool
    {
        // Ignore if we've already done this once during the request
        if ($this->_deletedExpiredTokens) {
            return false;
        }

        $affectedRows = Db::delete('{{%campaign_tokens}}', ['<=', 'expiryDate', Db::prepareDateForDb(new DateTime())]);

        $this->_deletedExpiredTokens = true;

        return (bool)$affectedRows;
    }
}