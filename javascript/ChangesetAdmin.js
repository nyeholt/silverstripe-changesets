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