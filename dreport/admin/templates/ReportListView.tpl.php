<?php
	$this->assign('title','DREPORTS | Reports');
	$this->assign('nav','reports');

	$this->display('_Header.tpl.php');
?>

<script type="text/javascript">
	$LAB.script("scripts/app/reports.js").wait(function(){
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
	<i class="icon-th-list"></i> Reports
	<span id=loader class="loader progress progress-striped active"><span class="bar"></span></span>
	<span class='input-append pull-right searchContainer'>
		<input id='filter' type="text" placeholder="Search..." />
		<button class='btn add-on'><i class="icon-search"></i></button>
	</span>
</h1>

	<!-- underscore template for the collection -->
	<script type="text/template" id="reportCollectionTemplate">
		<table class="collection table table-bordered table-hover">
		<thead>
			<tr>
				<th id="header_Id">Id<% if (page.orderBy == 'Id') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Objectid">Objectid<% if (page.orderBy == 'Objectid') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Name">Name<% if (page.orderBy == 'Name') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_FriendlynameBg">Friendlyname Bg<% if (page.orderBy == 'FriendlynameBg') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_FriendlynameEn">Friendlyname En<% if (page.orderBy == 'FriendlynameEn') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
                <th id="header_Appdbtype">Appdbtype<% if (page.orderBy == 'Appdbtype') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Order">Order<% if (page.orderBy == 'Order') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Color">Color<% if (page.orderBy == 'Color') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
<!-- UNCOMMENT TO SHOW ADDITIONAL COLUMNS
				<th id="header_Href">Href<% if (page.orderBy == 'Href') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_SqlBg">Sql Bg<% if (page.orderBy == 'SqlBg') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_SqlEn">Sql En<% if (page.orderBy == 'SqlEn') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
-->
			</tr>
		</thead>
		<tbody>
		<% items.each(function(item) { %>
			<tr id="<%= _.escape(item.get('id')) %>">
				<td><%= _.escape(item.get('id') || '') %></td>
				<td><%= _.escape(item.get('objectid') || '') %></td>
				<td><%= _.escape(item.get('name') || '') %></td>
				<td><%= _.escape(item.get('friendlynameBg') || '') %></td>
				<td><%= _.escape(item.get('friendlynameEn') || '') %></td>
                <td><%= _.escape(item.get('appdbtype') || '') %></td>
				<td><%= _.escape(item.get('order') || '') %></td>
				<td><%= _.escape(item.get('color') || '') %></td>
<!-- UNCOMMENT TO SHOW ADDITIONAL COLUMNS
				<td><%= _.escape(item.get('href') || '') %></td>
				<td><%= _.escape(item.get('sqlBg') || '') %></td>
				<td><%= _.escape(item.get('sqlEn') || '') %></td>
-->				
			</tr>
		<% }); %>
		</tbody>
		</table>

		<%=  view.getPaginationHtml(page) %>
	</script>

	<!-- underscore template for the model -->
	<script type="text/template" id="reportModelTemplate">
		<form class="form-horizontal" onsubmit="return false;">
			<fieldset>
				<div id="idInputContainer" class="control-group">
					<label class="control-label" for="id">Id</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="id" placeholder="Id" value="<%= _.escape(item.get('id') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="objectidInputContainer" class="control-group">
					<label class="control-label" for="objectid">Objectid</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="objectid" placeholder="Objectid" value="<%= _.escape(item.get('objectid') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="nameInputContainer" class="control-group">
					<label class="control-label" for="name">Name</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="name" placeholder="Name" value="<%= _.escape(item.get('name') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="friendlynameBgInputContainer" class="control-group">
					<label class="control-label" for="friendlynameBg">Friendlyname Bg</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="friendlynameBg" placeholder="Friendlyname Bg" value="<%= _.escape(item.get('friendlynameBg') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="friendlynameEnInputContainer" class="control-group">
					<label class="control-label" for="friendlynameEn">Friendlyname En</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="friendlynameEn" placeholder="Friendlyname En" value="<%= _.escape(item.get('friendlynameEn') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="hrefInputContainer" class="control-group">
					<label class="control-label" for="href">Href</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="href" placeholder="Href" value="<%= _.escape(item.get('href') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="sqlBgInputContainer" class="control-group">
					<label class="control-label" for="sqlBg">Sql Bg</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="sqlBg" placeholder="Sql Bg" value="<%= _.escape(item.get('sqlBg') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="sqlEnInputContainer" class="control-group">
					<label class="control-label" for="sqlEn">Sql En</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="sqlEn" placeholder="Sql En" value="<%= _.escape(item.get('sqlEn') || '') %>">
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
				<div id="orderInputContainer" class="control-group">
					<label class="control-label" for="order">Order</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="order" placeholder="Order" value="<%= _.escape(item.get('order') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="colorInputContainer" class="control-group">
					<label class="control-label" for="color">Color</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="color" placeholder="Color" value="<%= _.escape(item.get('color') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
			</fieldset>
		</form>

		<!-- delete button is is a separate form to prevent enter key from triggering a delete -->
		<form id="deleteReportButtonContainer" class="form-horizontal" onsubmit="return false;">
			<fieldset>
				<div class="control-group">
					<label class="control-label"></label>
					<div class="controls">
						<button id="deleteReportButton" class="btn btn-mini btn-danger"><i class="icon-trash icon-white"></i> Delete Report</button>
						<span id="confirmDeleteReportContainer" class="hide">
							<button id="cancelDeleteReportButton" class="btn btn-mini">Cancel</button>
							<button id="confirmDeleteReportButton" class="btn btn-mini btn-danger">Confirm</button>
						</span>
					</div>
				</div>
			</fieldset>
		</form>
	</script>

	<!-- modal edit dialog -->
	<div class="modal hide fade" id="reportDetailDialog">
		<div class="modal-header">
			<a class="close" data-dismiss="modal">&times;</a>
			<h3>
				<i class="icon-edit"></i> Edit Report
				<span id="modelLoader" class="loader progress progress-striped active"><span class="bar"></span></span>
			</h3>
		</div>
		<div class="modal-body">
			<div id="modelAlert"></div>
			<div id="reportModelContainer"></div>
		</div>
		<div class="modal-footer">
			<button class="btn" data-dismiss="modal" >Cancel</button>
			<button id="saveReportButton" class="btn btn-primary">Save Changes</button>
		</div>
	</div>

	<div id="collectionAlert"></div>
	
	<div id="reportCollectionContainer" class="collectionContainer">
	</div>

	<p id="newButtonContainer" class="buttonContainer">
		<button id="newReportButton" class="btn btn-primary">Add Report</button>
	</p>

</div> <!-- /container -->

<?php
	$this->display('_Footer.tpl.php');
?>
