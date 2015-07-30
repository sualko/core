/*
 * Copyright (c) 2015
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function() {
	var TEMPLATE =
		'<div><span>Owner: {{owner}}</span>' +
		'</div><div>' +
		'    <label for="shareWith" class="hidden-visually">{{shareLabel}}</label>' +
		'    <div class="oneline">' +
		'        <input id="shareWith" type="text" placeholder="{{sharePlaceholder}}" />' +
		'        <span class="shareWithLoading icon-loading-small hidden"></span>'+
		'    </div>' +
			// FIXME: find a good position for remoteShareInfo
		'    {{{remoteShareInfo}}}' +
		'    <ul id="shareWithList">' +
		'    </ul>' +
		'    {{{linkShare}}}' +
		'</div>';

	var TEMPLATE_REMOTE_SHARE_INFO =
		'<a target="_blank" class="icon-info svg shareWithRemoteInfo" href="{{docLink}}" ' +
		'title="{{tooltip}}"></a>';

	var TEMPLATE_LINK_SHARE =
		'<div id="link" class="linkShare">' +
		'<span class="icon-loading-small hidden"></span>' +
		'<input type="checkbox" name="linkCheckbox" id="linkCheckbox" value="1" /><label for="linkCheckbox">{{{linkShareLabel}}}</label>' +
		'<br />';

	/**
	 * @class OCA.Sharing.ShareTabView
	 * @classdesc
	 *
	 * Displays sharing information
	 *
	 */
	var ShareTabView = function(id) {
		this.initialize(id);
	};
	/**
	 * @memberof OCA.Sharing
	 */
	ShareTabView.prototype = _.extend({}, OCA.Files.DetailTabView.prototype,
		/** @lends OCA.Sharing.ShareTabView.prototype */ {
		_template: null,
		_remoteShareInfoTemplate: null,

		/** @var {string} localization app **/
		la: 'core',

		/**
		 * Initialize the details view
		 */
		initialize: function() {
			OCA.Files.DetailTabView.prototype.initialize.apply(this, arguments);
			this.$el.addClass('shareTabView');
		},

		getLabel: function() {
			return t('files_sharing', 'Sharing');
		},

		/**
		 * returns the info template for remote sharing
		 *
		 * @returns {Handlebars}
		 * @private
		 */
		_getRemoteShareInfoTemplate: function() {
			if(!this._remoteShareInfoTemplate) {
				this._remoteShareInfoTemplate = Handlebars.compile(TEMPLATE_REMOTE_SHARE_INFO);
			}
			return this._remoteShareInfoTemplate;
		},

		/**
		 * returns the info template for link sharing
		 *
		 * @returns {Handlebars}
		 * @private
		 */
		_getLinkShareTemplate: function() {
			if(!this._getLinkShareTemplate) {
				this._getLinkShareTemplate = Handlebars.compile(TEMPLATE_LINK_SHARE);
			}
			return this._getLinkShareTemplate;
		},

		_renderSharePlaceholderPart: function () {
			var sharePlaceholder = t(this.la, 'Share with users or groups …');
			if(oc_appconfig.core.remoteShareAllowed) {
				sharePlaceholder = t(this.la, 'Share with users, groups or remote users …');
			}
			return sharePlaceholder;
		},

		_renderRemoteShareInfoPart: function() {
			var remoteShareInfo = '';
			if(oc_appconfig.core.remoteShareAllowed) {
				var infoTemplate = this._getRemoteShareInfoTemplate();
				remoteShareInfo = infoTemplate({
					docLink: oc_appconfig.core.federatedCloudShareDoc,
					tooltip: t(this.la, 'Share with people on other ownClouds using the syntax username@example.com/owncloud')
				});
			}
			return remoteShareInfo;
		},

		_renderLinkSharePart: function() {
			var linkShare = '';
			if($('#allowShareWithLink').val() === 'yes') {
				var linkShareTemplate = this._getLinkShareTemplate();
				linkShare = linkShareTemplate({
					linkShareLabel: t(this.la, 'Share link')
				});
			}

			return linkShare;
		},

		/**
		 * Renders this details view
		 */
		render: function() {
			this.$el.empty();

			if (!this._template) {
				this._template = Handlebars.compile(TEMPLATE);
			}

			if (this._fileInfo) {
				this.$el.append(this._template({
					owner: this._fileInfo.shareOwner || OC.currentUser,
					shareLabel: t(this.la, 'Share'),
					sharePlaceholder: this._renderSharePlaceholderPart(),
					remoteShareInfo: this._renderRemoteShareInfoPart(),
					linkShare: this._renderLinkSharePart()
				}));

			} else {
				// TODO: render placeholder text?
			}
		}
	});

	OCA.Sharing.ShareTabView = ShareTabView;
})();

