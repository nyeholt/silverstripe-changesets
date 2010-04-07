<h2><% _t('CHANGESETS', 'Changesets') %></h2>

<div id="treepanes">
	<div id="sitetree_holder">
		<ul id="sitetree" class="tree unformatted">
			<li id="$ID" class="Root"><a><strong><% _t('CHANGESETS', 'Changesets') %></strong></a>
				<% if Changesets %>
				<ul>
					<% control Changesets %>
					<li id="$ID">
						<a href="{$BaseHref}admin/changeset-admin/showchangeset/$ID" title="">$Title</a>
					</li>
					<!-- all other users' changes-->
					<% end_control %>
				</ul>
				<% end_if %>
			</li>
		</ul>
	</div>
</div>