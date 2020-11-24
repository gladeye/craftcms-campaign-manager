if (typeof Craft.CampaignManager === typeof undefined) {
    Craft.CampaignManager = {};
}

Craft.CampaignManager.CampaignPreview = Garnish.Base.extend({
    $previewButton: null,
    previewToken: null,
    init: function (settings) {
        this.setSettings(settings, Craft.CampaignManager.CampaignPreview.defaults);
        this.$previewButton = $('#preview-campaign-btn');
        console.log(this.settings.open);
        this.addListener(this.$previewButton, 'click', 'openPreview');

    },
    openPreview: function (e) {
        this.getTokenizedPreviewUrl(this.settings.open).then(function(url) {
            console.log(url);
            window.open(url);
        }.bind(this), function(error) {
            console.log(error);
        })
    },
    getPreviewToken: function () {
        return new Promise(function (resolve, reject) {
            if (this.previewToken) {
                resolve(this.previewToken);
                return;
            }

            Craft.sendActionRequest('post', 'campaign-manager/campaigns/create-preview-token', {
                data: { campaignId: this.settings.campaignId}
            }).then(function (response) {
                if (response.status === 200) {
                    this.previewToken = response.data.token;
                    resolve(this.previewToken);
                } else {
                    reject();
                }
            }.bind(this));
        }.bind(this));
    },
    getTokenizedPreviewUrl: function(url) {
        return new Promise(function(resolve, reject) {
            var params = {};
            console.log(url);
            this.getPreviewToken().then(function(token) {
                console.log(token);
                params['campaign-token'] = token;
                resolve(Craft.getUrl(url, params));
            }).catch(reject);
        }.bind(this));
    }

}, {
    defaults: {
        campaignId: null,
        open: false
    }
})

