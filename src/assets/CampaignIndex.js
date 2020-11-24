/** global: Craft */
/** global: Garnish */
/**
 * Campaign index class
 */
if (typeof Craft.CampaignManager === typeof undefined) {
    Craft.CampaignManager = {};
}
Craft.CampaignManager.CampaignIndex = Craft.BaseElementIndex.extend(
    {
        publishableSections: null,
        $newEntryBtnGroup: null,
        $newEntryBtn: null,

        init: function(elementType, $container, settings) {
            this.on('selectSource', $.proxy(this, 'updateButton'));
            this.base(elementType, $container, settings);
        },



        updateButton: function() {
            if (!this.$source) {
                return;
            }

            var handle;

            // Get the handle of the selected source
            if (this.$source.data('key') === 'singles') {
                handle = 'singles';
            }
            else {
                handle = this.$source.data('handle');
            }

            // Update the New Entry button
            // ---------------------------------------------------------------------

            var i, href, label;

                // Remove the old button, if there is one
                if (this.$newEntryBtnGroup) {
                    this.$newEntryBtnGroup.remove();
                }

                // Determine if they are viewing a section that they have permission to create entries in

                this.$newEntryBtnGroup = $('<div class="btngroup submit"/>');
                var $menuBtn;

                // If they are, show a primary "New entry" button, and a dropdown of the other sections (if any).
                // Otherwise only show a menu button


            href = this._getHref();
            label = (this.settings.context === 'index' ? Craft.t('app', 'New campaign') : Craft.t('app', 'New campaign'));
            this.$newEntryBtn = $('<a class="btn submit add icon" ' + href + ' role="button" tabindex="0">' + Craft.escapeHtml(label) + '</a>').appendTo(this.$newEntryBtnGroup);

            if (this.settings.context !== 'index') {
                this.addListener(this.$newEntryBtn, 'click', function(ev) {
                    this._openCreateCampaignModal(ev.currentTarget.getAttribute('data-id'));
                });
            }

            this.addButton(this.$newEntryBtnGroup);

            // Update the URL if we're on the Entries index
            // ---------------------------------------------------------------------

            if (this.settings.context === 'index' && typeof history !== 'undefined') {
                var uri = 'campaigns';

                if (handle) {
                    uri += '/' + handle;
                }

                history.replaceState({}, '', Craft.getUrl(uri));
            }
        },
        _getHref: function() {
            if (this.settings.context === 'index') {
                var uri = 'campaigns/edit'
                let params = {};

                return 'href="' + Craft.getUrl(uri, params) + '"';
            }
            return '';
        },

        _openCreateCampaignModal: function() {
            if (this.$newEntryBtn.hasClass('loading')) {
                return;
            }

            // Find the section

            this.$newEntryBtn.addClass('inactive');
            var newEntryBtnText = this.$newEntryBtn.text();
            this.$newEntryBtn.text(Craft.t('app', 'New campaign'));

            Craft.createElementEditor(this.elementType, {
                hudTrigger: this.$newEntryBtnGroup,
                siteId: this.siteId,
                attributes: {
                    enabled: 1,
                },
                onBeginLoading: $.proxy(function() {
                    this.$newEntryBtn.addClass('loading');
                }, this),
                onEndLoading: $.proxy(function() {
                    this.$newEntryBtn.removeClass('loading');
                }, this),
                onHideHud: $.proxy(function() {
                    this.$newEntryBtn.removeClass('inactive').text(newEntryBtnText);
                }, this),
                onSaveElement: $.proxy(function(response) {
                    // Make sure the right section is selected

                    this.selectElementAfterUpdate(response.id);
                    this.updateElements();
                }, this)
            });
        }
    });

// Register it!
Craft.registerElementIndexClass('Gladeye\\CampaignManager\\elements\\Campaign', Craft.CampaignManager.CampaignIndex);
