<?php
	$this->assign('title','DREPORTS | Subscriptions');
	$this->assign('nav','subscriptions');

	$this->display('_Header.tpl.php');
?>

<script type="text/javascript">
	$LAB.script("scripts/app/subscriptions.js").wait(function(){
		$(document).ready(function(){
			page.init();
		});
		
		// hack for IE9 which may respond inconsistently with document.ready
		setTimeout(function(){
			if (!page.isInitialized) page.init();
		},1000);
	});
</script>

<div class="container">

<h1>
	<i class="icon-th-list"></i> Subscriptions
	<span id=loader class="loader progress progress-striped active"><span class="bar"></span></span>
	<span class='input-append pull-right searchContainer'>
		<input id='filter' type="text" placeholder="Search..." />
		<button class='btn add-on'><i class="icon-search"></i></button>
	</span>
</h1>

	<!-- underscore template for the collection -->
	<script type="text/template" id="subscriptionCollectionTemplate">
		<table class="collection table table-bordered table-hover">
		<thead>
			<tr>
				<th id="header_Objectid">Objectid<% if (page.orderBy == 'Objectid') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Objectname">Objectname<% if (page.orderBy == 'Objectname') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Expiredate">Expiredate<% if (page.orderBy == 'Expiredate') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Customername">Customername<% if (page.orderBy == 'Customername') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Eik">Eik<% if (page.orderBy == 'Eik') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
<!-- UNCOMMENT TO SHOW ADDITIONAL COLUMNS -->
				<th id="header_Address">Address<% if (page.orderBy == 'Address') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Hostname">Hostname<% if (page.orderBy == 'Hostname') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Appip">Appip<% if (page.orderBy == 'Appip') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Apptype">Apptype<% if (page.orderBy == 'Apptype') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Appver">Appver<% if (page.orderBy == 'Appver') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Appdbtype">Appdbtype<% if (page.orderBy == 'Appdbtype') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Active">Active<% if (page.orderBy == 'Active') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Createdate">Createdate<% if (page.orderBy == 'Createdate') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Lastupdatedate">Lastupdatedate<% if (page.orderBy == 'Lastupdatedate') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Comment">Comment<% if (page.orderBy == 'Comment') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
<!-- -->
			</tr>
		</thead>
		<tbody>
		<% items.each(function(item) { %>
			<tr id="<%= _.escape(item.get('objectid')) %>">
				<td><%= _.escape(item.get('objectid') || '') %></td>
				<td><%= _.escape(item.get('objectname') || '') %></td>
				<td><%if (item.get('expiredate')) { %><%= _date(app.parseDate(item.get('expiredate'))).format('DD.MM.YYYY') %><% } else { %>NULL<% } %></td>
				<td><%= _.escape(item.get('customername') || '') %></td>
				<td><%= _.escape(item.get('eik') || '') %></td>
<!-- UNCOMMENT TO SHOW ADDITIONAL COLUMNS -->
				<td><%= _.escape(item.get('address') || '') %></td>
				<td><%= _.escape(item.get('hostname') || '') %></td>
				<td><%= _.escape(item.get('appip') || '') %></td>
				<td><%= _.escape(item.get('apptype') || '') %></td>
				<td><%= _.escape(item.get('appver') || '') %></td>
				<td><%= _.escape(item.get('appdbtype') || '') %></td>
				<td><%= _.escape(item.get('active') || '') %></td>
				<td><%if (item.get('createdate')) { %><%= _date(app.parseDate(item.get('createdate'))).format('DD.MM.YYYY HH:mm:ss') %><% } else { %>NULL<% } %></td>
				<td><%if (item.get('lastupdatedate')) { %><%= _date(app.parseDate(item.get('lastupdatedate'))).format('DD.MM.YYYY HH:mm:ss') %><% } else { %>NULL<% } %></td>
				<td><%= _.escape(item.get('comment') || '') %></td>
<!-- -->
			</tr>
		<% }); %>
		</tbody>
		</table>

		<%=  view.getPaginationHtml(page) %>
	</script>

	<!-- underscore template for the model -->
	<script type="text/template" id="subscriptionModelTemplate">
		<form class="form-horizontal" onsubmit="return false;">
			<fieldset>
				<div id="objectidInputContainer" class="control-group">
					<label class="control-label" for="objectid">Objectid</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="objectid" placeholder="Objectid" value="<%= _.escape(item.get('objectid') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="objectnameInputContainer" class="control-group">
					<label class="control-label" for="objectname">Objectname</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="objectname" placeholder="Objectname" value="<%= _.escape(item.get('objectname') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="expiredateInputContainer" class="control-group">
					<label class="control-label" for="expiredate">Expiredate</label>
					<div class="controls inline-inputs">
						<div class="input-append date date-picker" data-date-format="yyyy-mm-dd">
							<input id="expiredate" type="text" value="<%= _date(app.parseDate(item.get('expiredate'))).format('YYYY-MM-DD') %>" />
							<span class="add-on"><i class="icon-calendar"></i></span>
						</div>
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="customernameInputContainer" class="control-group">
					<label class="control-label" for="customername">Customername</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="customername" placeholder="Customername" value="<%= _.escape(item.get('customername') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="eikInputContainer" class="control-group">
					<label class="control-label" for="eik">Eik</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="eik" placeholder="Eik" value="<%= _.escape(item.get('eik') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="addressInputContainer" class="control-group">
					<label class="control-label" for="address">Address</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="address" placeholder="Address" value="<%= _.escape(item.get('address') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="hostnameInputContainer" class="control-group">
					<label class="control-label" for="hostname">Hostname</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="hostname" placeholder="Hostname" value="<%= _.escape(item.get('hostname') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="appipInputContainer" class="control-group">
					<label class="control-label" for="appip">Appip</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="appip" placeholder="Appip" value="<%= _.escape(item.get('appip') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="apptypeInputContainer" class="control-group">
					<label class="control-label" for="apptype">Apptype</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="apptype" placeholder="Apptype" value="<%= _.escape(item.get('apptype') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="appverInputContainer" class="control-group">
					<label class="control-label" for="appver">Appver</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="appver" placeholder="Appver" value="<%= _.escape(item.get('appver') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="appdbtypeInputContainer" class="control-group">
					<label class="control-label" for="appdbtype">Appdbtype</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="appdbtype" placeholder="Appdbtype" value="<%= _.escape(item.get('appdbtype') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="activeInputContainer" class="control-group">
					<label class="control-label" for="active">Active</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="active" placeholder="Active" value="<%= _.escape(item.get('active') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="createdateInputContainer" class="control-group">
					<label class="control-label" for="createdate">Createdate</label>
					<div class="controls inline-inputs">
						<div class="input-append date date-picker" data-date-format="yyyy-mm-dd">
							<input id="createdate" type="text" value="<%= _date(app.parseDate(item.get('createdate'))).format('YYYY-MM-DD') %>" />
							<span class="add-on"><i class="icon-calendar"></i></span>
						</div>
						<div class="input-append bootstrap-timepicker-component">
							<input id="createdate-time" type="text" class="timepicker-default input-small" value="<%= _date(app.parseDate(item.get('createdate'))).format('h:mm A') %>" />
							<span class="add-on"><i class="icon-time"></i></span>
						</div>
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="lastupdatedateInputContainer" class="control-group">
					<label class="control-label" for="lastupdatedate">Lastupdatedate</label>
					<div class="controls inline-inputs">
						<div class="input-append date date-picker" data-date-format="yyyy-mm-dd">
							<input id="lastupdatedate" type="text" value="<%= _date(app.parseDate(item.get('lastupdatedate'))).format('YYYY-MM-DD') %>" />
							<span class="add-on"><i class="icon-calendar"></i></span>
						</div>
						<div class="input-append bootstrap-timepicker-component">
							<input id="lastupdatedate-time" type="text" class="timepicker-default input-small" value="<%= _date(app.parseDate(item.get('lastupdatedate'))).format('h:mm A') %>" />
							<span class="add-on"><i class="icon-time"></i></span>
						</div>
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="commentInputContainer" class="control-group">
					<label class="control-label" for="comment">Comment</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="comment" placeholder="Comment" value="<%= _.escape(item.get('comment') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
			</fieldset>
		</form>

		<!-- delete button is is a separate form to prevent enter key from triggering a delete -->
		<form id="deleteSubscriptionButtonContainer" class="form-horizontal" onsubmit="return false;">
			<fieldset>
				<div class="control-group">
					<label class="control-label"></label>
					<div class="controls">
						<button id="deleteSubscriptionButton" class="btn btn-mini btn-danger"><i class="icon-trash icon-white"></i> Delete Subscription</button>
						<span id="confirmDeleteSubscriptionContainer" class="hide">
							<button id="cancelDeleteSubscriptionButton" class="btn btn-mini">Cancel</button>
							<button id="confirmDeleteSubscriptionButton" class="btn btn-mini btn-danger">Confirm</button>
						</span>
					</div>
				</div>
			</fieldset>
		</form>
	</script>

	<!-- modal edit dialog -->
	<div class="modal hide fade" id="subscriptionDetailDialog">
		<div class="modal-header">
			<a class="close" data-dismiss="modal">&times;</a>
			<h3>
				<i class="icon-edit"></i> Edit Subscription
				<span id="modelLoader" class="loader progress progress-striped active"><span class="bar"></span></span>
			</h3>
		</div>
		<div class="modal-body">
			<div id="modelAlert"></div>
			<div id="subscriptionModelContainer"></div>
		</div>
		<div class="modal-footer">
			<button class="btn" data-dismiss="modal" >Cancel</button>
			<button id="saveSubscriptionButton" class="btn btn-primary">Save Changes</button>
		</div>
	</div>

	<div id="collectionAlert"></div>
	
	<div id="subscriptionCollectionContainer" class="collectionContainer">
	</div>


</div> <!-- /container -->

<?php
	$this->display('_Footer.tpl.php');
?>
