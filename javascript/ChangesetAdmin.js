if(typeof SiteTreeHandlers == 'undefined') SiteTreeHandlers = {};
SiteTreeHandlers.parentChanged_url = 'admin/changeset-admin/ajaxupdateparent';
SiteTreeHandlers.orderChanged_url = 'admin/changeset-admin/ajaxupdatesort';
SiteTreeHandlers.loadPage_url = 'admin/changeset-admin/getitem';
SiteTreeHandlers.loadTree_url = 'admin/changeset-admin/getsubtree';
SiteTreeHandlers.showRecord_url = 'admin/changeset-admin/show/';
SiteTreeHandlers.controller_url = 'admin/changeset-admin';

Behaviour.register({
	'#Form_EditForm' : {
		getPageFromServer : function(id) {
			statusMessage("loading...");

			var requestURL = 'admin/changeset-admin/showchangeset/' + id;

			this.loadURLFromServer(requestURL);

			$('sitetree').setCurrentByIdx(id);
		}
	}
});