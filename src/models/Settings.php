<?php
namespace Gladeye\CampaignManager\models;

use craft\base\Model;

class Settings extends Model {
    public $tokenParam = 'campaign-token';

    public function rules()
    {
        return [
            [['tokenParam'], 'required']
        ];
    }
}