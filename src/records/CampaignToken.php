<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace Gladeye\CampaignManager\records;

use craft\db\ActiveRecord;
use craft\db\Table;
use craft\validators\DateTimeValidator;
use yii\db\ActiveQueryInterface;

/**
 * Campaign Token record.
 *
 * @property int $id ID
 * @property string $token Token
 * @property \DateTime $expiryDate Expiry date
 * @property int $campaignId Campaign
 */
class CampaignToken extends ActiveRecord
{

    public function rules()
    {
        return [
            [['expiryDate'], DateTimeValidator::class],
            [['token'], 'unique'],
            [['token', 'expiryDate'], 'required'],
            [['token'], 'string', 'length' => 32],
        ];
    }
    /**
     * @inheritdoc
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%campaign_tokens}}';
    }

    public function getCampaign() : ActiveQueryInterface {
        return $this->hasOne(Campaign::class, ['id' => 'campaignId']);
    }
}
