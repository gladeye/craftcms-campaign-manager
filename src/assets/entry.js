if (typeof Craft.CampaignManager === typeof undefined) {
    Craft.CampaignManager = {};
}
Craft.CampaignManager.Entry = Garnish.Base.extend({
    $actionButtons: null,
    $addToCampaignButton: null,
    $actionsContainer: null,
    $revisionBtn: null,
    $revisionLabel: null,
    $header: null,
    $entryTitle: null,
    $message: null,
    $previewInCampaignBtn: null,
    modal: null,
    init: function(settings) {
        this.setSettings(settings, Craft.CampaignManager.Entry.defaults);
        this.$actionButtons = $('#action-buttons');
        this.$revisionBtn = $('#context-btn');
        this.$header = $('#header');
        this.$entryTitle = this.$header.children().first();
        this.$revisionLabel = $('#revision-label');


        this.updateActions();
        this.updateMessage();
        this.updateRevisionsMenu();
        if(window.draftEditor) {
            window.draftEditor.on('update', function(e) {
                this.settings.draftId = e.target.settings.draftId;
                this.settings.draftName = e.target.settings.draftName;
                this.settings.isDraft = !!e.target.settings.draftId;
                this.updateActions();
                this.updateRevisionsMenu();
                this.updateMessage();
            }.bind(this));
        }
    },

    updateActions: function() {
        if(this.$actionsContainer) {
            this.$actionsContainer.remove();
        }
        if(this.$previewInCampaignBtn) {
            this.$previewInCampaignBtn.remove();
        }
        this.$actionsContainer = $('<div></div>');
        this.$actionButtons.append(this.$actionsContainer);
        if(this.settings.campaign) {
            let removeBtn = $('<button type="button" class="btn secondary">Remove from campaign: '+this.settings.campaign.name+'</button>');
            this.$actionsContainer.append(removeBtn);
            this.addListener(removeBtn, 'click', 'removeFromCampaign');
            this.$previewInCampaignBtn = $('<a href="' + Craft.getCpUrl('campaigns/' + this.settings.campaign.id + '/preview', {draftId: this.settings.draftId}) + '" target="_blank" class="btn">Preview in Campaign</a>');
            this.$actionButtons.find('#preview-btn').after(this.$previewInCampaignBtn);
        } else {
            let btn = $('<button type="button" class="btn secondary">Add to campaign</button>');
            this.$actionsContainer.append(btn);
            this.addListener(btn, 'click', 'showModal');
        }


    },

    updateMessage: function() {
        if(this.$message) {
            this.$message.remove();
        }
        let numCampaigns = Object.values(this.settings.campaigns).length
        if(numCampaigns > 0 && !this.settings.campaign) {

            let $message = $('<div class="message">This entry has '+ numCampaigns + ' draft' + (numCampaigns !== 1 ? 's' : '') + ' that belong to campaigns</div>')
            this.$entryTitle.append($message);
        }
    },

    updateRevisionsMenu: function() {

        var revisionMenu = this.$revisionBtn.data('menubtn') ? this.$revisionBtn.data('menubtn').menu : null;

        if(this.settings.campaigns) {
            $('.revision-group-drafts').find('a').each(function(i, option) {
                let $option = $(option);
                let url = new URL($option.attr('href'));
                let draftId = url.searchParams.get('draftId');
                let campaign = this.settings.campaigns[draftId];
                if(campaign) {
                    $option.find('.draft-name').html(campaign.draftName + ' (<span class="draft-in-campaign">' + campaign.campaign.name + '</span>)');
                }
            }.bind(this))
        }

        let draftName = this.settings.draftName;
        if(this.settings.campaign) {
            console.log(this.settings.campaigns);

            this.$revisionLabel.text(draftName + ' (' + this.settings.campaign.name + ')');
        } else if(draftName) {
            this.$revisionLabel.text(draftName);
        }

    },

    showModal: function() {
        // Make sure we haven't reached the limit
        if(!this.settings.isDraft) {
            window.draftEditor.createDraft().then(() => {
                this.settings.isDraft = true;
                if (!this.modal) {
                    this.modal = this.createModal();
                }
                else {
                    this.modal.show();
                }
            })
            window.dradtEditor.removeListener(Craft.cp.$primaryForm, 'submit.saveShortcut');
        } else {
            if (!this.modal) {
                this.modal = this.createModal();
            }
            else {
                this.modal.show();
            }
        }

    },

    createModal: function() {
        return Craft.createElementSelectorModal('Gladeye\\CampaignManager\\elements\\Campaign', this.getModalSettings());
    },

    getModalSettings: function() {
        let disabledElementIds = this.settings.campaigns ? Object.values(this.settings.campaigns).map(campaign => {
            return campaign.campaign.id;
        }) : [];

        return $.extend({
            closeOtherModals: false,
            storageKey: 'elementindex.Gladeye\\CampaignManager\\elements\\Campaign',
            sources: ['*'],
            criteria: Craft.defaultIndexCriteria,
            multiSelect: false,
            showSiteMenu: false,
            disabledElementIds: disabledElementIds,
            onSelect: $.proxy(this, 'onModalSelect')
        }, this.settings.modalSettings);
    },
    onModalSelect: function(elements) {
        var campaign = elements[0];
        this.addToCampaign(campaign);


    },
    addToCampaign: function(campaign) {
        Craft.sendActionRequest('post', 'campaign-manager/campaigns/add-to-campaign', {
            data: { campaignId: campaign.id, draftId: this.settings.draftId}
        }).then(function(response) {
            this.settings.campaign = response.data.campaign;
            this.updateActions();
            this.updateRevisionsMenu();
        }.bind(this))
    },
    removeFromCampaign: function() {
        if(!this.settings.campaign) return;
        Craft.sendActionRequest('post', 'campaign-manager/campaigns/remove-draft', {
            data: { campaignId: this.settings.campaign.id, draftId: this.settings.draftId}
        }).then(function(response) {
            delete this.settings.campaign[this.settings.draftId];
            this.settings.campaign = null;
            this.updateActions();
            this.updateRevisionsMenu();
        }.bind(this))
    }
}, {
    defaults: {
        modalSettings: {},
        isDraft: false,
        entryId: null,
        draftId: null,
        campaign: null,
        campaigns: null,
        entry: null,
    }
})