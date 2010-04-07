<div id="form_actions_right" class="ajaxActions">
</div>

<% if EditForm %>
	$EditForm
<% else %>
	<form id="Form_EditForm" action="admin/changeset-admin?executeForm=EditForm" method="post" enctype="multipart/form-data">
		<p><% _t('SELECT_USER', 'Select a user to view changesets for') %></p>
	</form>
<% end_if %>

<p id="statusMessage" style="visibility:hidden"></p>
