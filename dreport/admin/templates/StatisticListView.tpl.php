<?php
	$this->assign('title','DREPORTS | Statistics');
	$this->assign('nav','statisticses');

	$this->display('_Header.tpl.php');
?>

<script type="text/javascript">
	$LAB.script("scripts/app/statisticses.js").wait(function(){
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
	<i class="icon-th-list"></i> Statistics
	<span id=loader class="loader progress progress-striped active"><span class="bar"></span></span>
	<span class='input-append pull-right searchContainer'>

 
        <input id='filter' type="text" placeholder="Search..." />
		<button class='btn add-on'><i class="icon-search"></i></button>
    
        <label class="control-label" for="filterDateFrom" style="display: inline-block; margin-top: 12px;">&nbsp; Date from: &nbsp;</label>        
        <input id='filterDateFrom' type="date" value="<?php echo date('Y-m-d',strtotime('-1 day')) ; ?>"  />
        <label class="control-label" for="filterDateTo" style="display: inline-block; margin-top: 12px;">&nbsp; to: &nbsp;</label>
        <input id='filterDateTo' type="date" value="<?php echo date('Y-m-d'); ?>" />
             
	</span>
    

</h1>



	<!-- underscore template for the collection -->
	<script type="text/template" id="statisticCollectionTemplate">
		<table class="collection table table-bordered table-hover">
		<thead>
			<tr>
				<th id="header_Id">Id<% if (page.orderBy == 'Id') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Opertype">Opertype<% if (page.orderBy == 'Opertype') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Operid">Operid<% if (page.orderBy == 'Operid') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Datetime">Datetime<% if (page.orderBy == 'Datetime') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
				<th id="header_Description">Description<% if (page.orderBy == 'Description') { %> <i class='icon-arrow-<%= page.orderDesc ? 'up' : 'down' %>' /><% } %></th>
			</tr>
		</thead>
		<tbody>
		<% items.each(function(item) { %>
			<tr id="<%= _.escape(item.get('id')) %>">
				<td><%= _.escape(item.get('id') || '') %></td>
				<td><%= _.escape(item.get('operationType') || '') %></td>
				<td><%= _.escape(item.get('operid') || '') %></td>
				<td><%if (item.get('datetime')) { %><%= _date(app.parseDate(item.get('datetime'))).format('DD.MM.YYYY HH:mm:ss') %><% } else { %>NULL<% } %></td>
				<td><%= _.escape(item.get('description') || '') %></td>
			</tr>
		<% }); %>
		</tbody>
		</table>

		<%=  view.getPaginationHtml(page) %>
	</script>

	<!-- underscore template for the model -->    
	<script type="text/template" id="statisticModelTemplate">
		<form class="form-horizontal" onsubmit="return false;">
			<fieldset>
				<div id="idInputContainer" class="control-group">
					<label class="control-label" for="id">Id</label>
					<div class="controls inline-inputs">
						<span class="input-xlarge uneditable-input" id="id"><%= _.escape(item.get('id') || '') %></span>
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="opertypeInputContainer" class="control-group">
					<label class="control-label" for="opertype">Opertype</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="opertype" placeholder="Opertype" value="<%= _.escape(item.get('opertype') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="operidInputContainer" class="control-group">
					<label class="control-label" for="operid">Operid</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="operid" placeholder="Operid" value="<%= _.escape(item.get('operid') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="datetimeInputContainer" class="control-group">
					<label class="control-label" for="datetime">Datetime</label>
					<div class="controls inline-inputs">
						<div class="input-append date date-picker" data-date-format="yyyy-mm-dd">
							<input id="datetime" type="text" value="<%= _date(app.parseDate(item.get('datetime'))).format('YYYY-MM-DD') %>" />
							<span class="add-on"><i class="icon-calendar"></i></span>
						</div>
						<div class="input-append bootstrap-timepicker-component">
							<input id="datetime-time" type="text" class="timepicker-default input-small" value="<%= _date(app.parseDate(item.get('datetime'))).format('h:mm A') %>" />
							<span class="add-on"><i class="icon-time"></i></span>
						</div>
						<span class="help-inline"></span>
					</div>
				</div>
				<div id="descriptionInputContainer" class="control-group">
					<label class="control-label" for="description">Description</label>
					<div class="controls inline-inputs">
						<input type="text" class="input-xlarge" id="description" placeholder="Description" value="<%= _.escape(item.get('description') || '') %>">
						<span class="help-inline"></span>
					</div>
				</div>
			</fieldset>
		</form>

		<!-- delete button is is a separate form to prevent enter key from triggering a delete -->
		<form id="deleteStatisticButtonContainer" class="form-horizontal" onsubmit="return false;">
			<fieldset>
				<div class="control-group">
					<label class="control-label"></label>
					<div class="controls">
						<button id="deleteStatisticButton" class="btn btn-mini btn-danger"><i class="icon-trash icon-white"></i> Delete Statistic</button>
						<span id="confirmDeleteStatisticContainer" class="hide">
							<button id="cancelDeleteStatisticButton" class="btn btn-mini">Cancel</button>
							<button id="confirmDeleteStatisticButton" class="btn btn-mini btn-danger">Confirm</button>
						</span>
					</div>
				</div>
			</fieldset>
		</form>
	</script>

	<!-- modal edit dialog -->
	<div class="modal hide fade" id="statisticDetailDialog">
		<div class="modal-header">
			<a class="close" data-dismiss="modal">&times;</a>
			<h3>
				<i class="icon-edit"></i> Edit Statistic
				<span id="modelLoader" class="loader progress progress-striped active"><span class="bar"></span></span>
			</h3>
		</div>
		<div class="modal-body">
			<div id="modelAlert"></div>
			<div id="statisticModelContainer"></div>
		</div>
		<div class="modal-footer">
			<button class="btn" data-dismiss="modal" >Cancel</button>
			<button id="saveStatisticButton" class="btn btn-primary">Save Changes</button>
		</div>
	</div>

	<div id="collectionAlert"></div>
	
	<div id="statisticCollectionContainer" class="collectionContainer">
	</div>
<!---
	<p id="newButtonContainer" class="buttonContainer">
		<button id="newStatisticButton" class="btn btn-primary">Add Statistic</button>
	</p>
-->    

</div> <!-- /container -->

<div class="container">
        <p class="muted"><small>"opertype" meaning: 0 - start application from mobile device, 1 - report request, 2 - add object/location, 3 - delete object/location, 4 - rest operation, 5 - error</small></p>
    </div>



<?php
	$this->display('_Footer.tpl.php');
?>
