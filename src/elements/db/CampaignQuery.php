<?php
namespace Gladeye\CampaignManager\elements\db;
use Craft;
use craft\db\QueryAbortedException;
use DateTime;
use Gladeye\CampaignManager\elements\Campaign;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use yii\db\Connection;

class CampaignQuery extends ElementQuery
{
    // Properties
    // =========================================================================
    public $name;

    public $description;

    public $publishedAt;

    public $isPublished;

    public $state;

    public $publishDue;


    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        parent::__construct($elementType, $config);
    }

    public function name($value) {
        $this->name = $value;
        return $this;
    }
    public function description($value) {
        $this->description = $value;
        return $this;
    }
    public function publishDue($value) {
        $this->publishDue = $value;
        return $this;
    }

    public function isPublished(bool $value)
    {
        $this->isPublished = $value;
        return $this;
    }


    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('campaigns');

        $this->query->select([
            'campaigns.name',
            'campaigns.description',
            'campaigns.publishedAt',
            'campaigns.state'
        ]);

        if ($this->name) {
            $this->subQuery->andWhere(Db::parseParam('campaigns.name', $this->name));
        }

        if ($this->description) {
            $this->subQuery->andWhere(Db::parseParam('campaigns.description', $this->description));
        }

        if($this->isPublished !== null) {

            if($this->isPublished === true) {
                $this->subQuery->andWhere(Db::parseParam('campaigns.publishedAt', Db::prepareDateForDb(new DateTime()), '<=' ));
            } else {
                $this->subQuery->andWhere('campaigns.publishedAt IS NULL');
            }
        }
        if($this->state) {
            $this->subQuery->andWhere(Db::parseParam('campaigns.state', $this->state));
        }

        if($this->publishDue) {
            $this->subQuery->andWhere(Db::parseParam('campaigns.state', Campaign::STATUS_PENDING));
            $this->subQuery->andWhere(Db::parseParam('campaigns.publishedAt', Db::prepareDateForDb(new DateTime()), '<=' ));
        }


        return parent::beforePrepare();
    }

}