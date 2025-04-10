<?php
	$this->assign('title','dadmin | Devices');
	$this->assign('nav','devices');

	$this->display('_Header.tpl.php');
?>

<script type="text/javascript">
	$LAB.script("scripts/app/devices.js").wait(function(){
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
	<i class="icon-th-list"></i> Devices
	<span id=loader class="loader progress progress-striped active"><span class="bar"></span></span>
	<span class='input-append pull-right searchContainer'>
		<input id='filter' type="text" placeholder="Search..." />
		<button class='btn add-on'><i class="icon-search"></i></button>
	</span>
</h1>

	<!-- underscore template for the collection -->
	<script type="text/template" id="deviceCollectionTemplate">
		<table class="collection table table-bordered table-hover">
		<thead>
			<tr>
				<th id="header_Id">Id<% if (page.orderBy == 'Id') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Deviceid">Deviceid<% if (page.orderBy == 'Deviceid') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Objectname">Objectname<% if (page.orderBy == 'Objectname') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Objectid">Objectid<% if (page.orderBy == 'Objectid') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Objectpswd">Objectpswd<% if (page.orderBy == 'Objectpswd') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
			</tr>
		</thead>
		<tbody>
		<% items.each(function(item) { %>
			<tr id="<%= _.escape(item.get('id')) %>">
				<td><%= _.escape(item.get('id') || '') %></td>
				<td><%= _.escape(item.get('deviceid') || '') %></td>
				<td><%= _.escape(item.get('objectname') || '') %></td>
				<td><%= _.escape(item.get('objectid') || '') %></td>
				<td><%= _.escape(item.get('objectpswd') || '') %></td>
			</tr>
		<% }); %>
		</tbody>
		</table>

		<%=  view.getPaginationHtml(page) %>
	</script>

	<!-- underscore template for the model -->
	<script type="text/template" id="deviceModelTemplate">
		<form class="form-horizontal" onsubmit="return false;">
			<fieldset>
				<div id="idInputContainer" class="control-group">
					<label class="control-label" for="id">Id</label>
					<div class="controls inline-inputs">
						<span class="input-xlarge uneditable-input" id="id"><%= _.escape(item.get('id') || '') %></span>
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="deviceidInputContainer" class="control-group">
					<label class="control-label" for="deviceid">Deviceid</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="deviceid" placeholder="Deviceid" value="<%= _.escape(item.get('deviceid') || '') %>">
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
				<div id="objectidInputContainer" class="control-group">
					<label class="control-label" for="objectid">Objectid</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="objectid" placeholder="Objectid" value="<%= _.escape(item.get('objectid') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="objectpswdInputContainer" class="control-group">
					<label class="control-label" for="objectpswd">Objectpswd</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="objectpswd" placeholder="Objectpswd" value="<%= _.escape(item.get('objectpswd') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
			</fieldset>
		</form>

		<!-- delete button is is a separate form to prevent enter key from triggering a delete -->
		<form id="deleteDeviceButtonContainer" class="form-horizontal" onsubmit="return false;">
			<fieldset>
				<div class="control-group">
					<label class="control-label"></label>
					<div class="controls">
						<button id="deleteDeviceButton" class="btn btn-mini btn-danger"><i class="icon-trash icon-white"></i> Delete Device</button>
						<span id="confirmDeleteDeviceContainer" class="hide">
							<button id="cancelDeleteDeviceButton" class="btn btn-mini">Cancel</button>
							<button id="confirmDeleteDeviceButton" class="btn btn-mini btn-danger">Confirm</button>
						</span>
					</div>
				</div>
			</fieldset>
		</form>
	</script>

	<!-- modal edit dialog -->
	<div class="modal hide fade" id="deviceDetailDialog">
		<div class="modal-header">
			<a class="close" data-dismiss="modal">&times;</a>
			<h3>
				<i class="icon-edit"></i> Edit Device
				<span id="modelLoader" class="loader progress progress-striped active"><span class="bar"></span></span>
			</h3>
		</div>
		<div class="modal-body">
			<div id="modelAlert"></div>
			<div id="deviceModelContainer"></div>
		</div>
		<div class="modal-footer">
			<button class="btn" data-dismiss="modal" >Cancel</button>
			<button id="saveDeviceButton" class="btn btn-primary">Save Changes</button>
		</div>
	</div>

	<div id="collectionAlert"></div>
	
	<div id="deviceCollectionContainer" class="collectionContainer">
	</div>

	<p id="newButtonContainer" class="buttonContainer">
		<button id="newDeviceButton" class="btn btn-primary">Add Device</button>
	</p>

</div> <!-- /container -->

<?php
	$this->display('_Footer.tpl.php');
?>
