<?php
namespace Gladeye\CampaignManager\bundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class EntryBundle extends AssetBundle
{
    public function init()
    {
        // define the path that your publishable resources live
        $this->sourcePath = '@Gladeye/CampaignManager/assets';

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];

        // define the relative path to CSS/JS files that should be registered with the page
        // when this asset bundle is registered
        $this->js = [
            'entry.js',
            'CampaignIndex.js'
        ];

//        $this->css = [
//            'styles.css',
//        ];

        parent::init();
    }
}
