<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace Gladeye\CampaignManager\records;

use craft\db\ActiveRecord;
use craft\db\Table;
use craft\records\Element;
use craft\records\Entry;
use yii\db\ActiveQueryInterface;

/**
 * Class Campaign record.
 *
 * @property int $id ID
 * @property int|null $authorId Author ID
 * @property \DateTime $publishedAt Post date
 * @property \DateTime $expiryDate Expiry date
 */
class Campaign extends ActiveRecord
{
    /**
     * @inheritdoc
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%campaigns}}';
    }

    public function getCampaignTokens() : ActiveQueryInterface {
        return $this->hasMany(CampaignToken::class, ['campaignId' => 'id']);
    }
}
