/**
 * backbone model definitions for DREPORTS
 */

/**
 * Use emulated HTTP if the server doesn't support PUT/DELETE or application/json requests
 */
Backbone.emulateHTTP = false;
Backbone.emulateJSON = false;

var model = {};

/**
 * long polling duration in miliseconds.  (5000 = recommended, 0 = disabled)
 * warning: setting this to a low number will increase server load
 */
model.longPollDuration = 0;

/**
 * whether to refresh the collection immediately after a model is updated
 */
model.reloadCollectionOnModelUpdate = true;


/**
 * a default sort method for sorting collection items.  this will sort the collection
 * based on the orderBy and orderDesc property that was used on the last fetch call
 * to the server. 
 */
model.AbstractCollection = Backbone.Collection.extend({
	totalResults: 0,
	totalPages: 0,
	currentPage: 0,
	pageSize: 0,
	orderBy: '',
	orderDesc: false,
	lastResponseText: null,
	lastRequestParams: null,
	collectionHasChanged: true,
	
	/**
	 * fetch the collection from the server using the same options and 
	 * parameters as the previous fetch
	 */
	refetch: function() {
		this.fetch({ data: this.lastRequestParams })
	},
	
	/* uncomment to debug fetch event triggers
	fetch: function(options) {
            this.constructor.__super__.fetch.apply(this, arguments);
	},
	// */
	
	/**
	 * client-side sorting baesd on the orderBy and orderDesc parameters that
	 * were used to fetch the data from the server.  Backbone ignores the
	 * order of records coming from the server so we have to sort them ourselves
	 */
	comparator: function(a,b) {
		
		var result = 0;
		var options = this.lastRequestParams;
		
		if (options && options.orderBy) {
			
			// lcase the first letter of the property name
			var propName = options.orderBy.charAt(0).toLowerCase() + options.orderBy.slice(1);
			var aVal = a.get(propName);
			var bVal = b.get(propName);
			
			if (isNaN(aVal) || isNaN(bVal)) {
				// treat comparison as case-insensitive strings
				aVal = aVal ? aVal.toLowerCase() : '';
				bVal = bVal ? bVal.toLowerCase() : '';
			} else {
				// treat comparision as a number
				aVal = Number(aVal);
				bVal = Number(bVal);
			}
			
			if (aVal < bVal) {
				result = options.orderDesc ? 1 : -1;
			} else if (aVal > bVal) {
				result = options.orderDesc ? -1 : 1;
			}
		}
		
		return result;

	},
	/**
	 * override parse to track changes and handle pagination
	 * if the server call has returned page data
	 */
	parse: function(response, options) {

		// the response is already decoded into object form, but it's easier to
		// compary the stringified version.  some earlier versions of backbone did
		// not include the raw response so there is some legacy support here
		var responseText = options && options.xhr ? options.xhr.responseText : JSON.stringify(response);
		this.collectionHasChanged = (this.lastResponseText != responseText);
		this.lastRequestParams = options ? options.data : undefined;
		
		// if the collection has changed then we need to force a re-sort because backbone will
		// only resort the data if a property in the model has changed
		if (this.lastResponseText && this.collectionHasChanged) this.sort({ silent:true });
		
		this.lastResponseText = responseText;
		
		var rows;

		if (response.currentPage) {
			rows = response.rows;
			this.totalResults = response.totalResults;
			this.totalPages = response.totalPages;
			this.currentPage = response.currentPage;
			this.pageSize = response.pageSize;
			this.orderBy = response.orderBy;
			this.orderDesc = response.orderDesc;
		} else {
			rows = response;
			this.totalResults = rows.length;
			this.totalPages = 1;
			this.currentPage = 1;
			this.pageSize = this.totalResults;
			this.orderBy = response.orderBy;
			this.orderDesc = response.orderDesc;
		}

		return rows;
	}
});

/**
 * Role Backbone Model
 */
model.RoleModel = Backbone.Model.extend({
	urlRoot: 'api/role',
	idAttribute: 'id',
	id: '',
	name: '',
	canAdmin: '',
	canEdit: '',
	canWrite: '',
	canRead: '',
	defaults: {
		'id': null,
		'name': '',
		'canAdmin': '',
		'canEdit': '',
		'canWrite': '',
		'canRead': ''
	}
});

/**
 * Role Backbone Collection
 */
model.RoleCollection = model.AbstractCollection.extend({
	url: 'api/roles',
	model: model.RoleModel
});

/**
 * Device Backbone Model
 *vll
model.DeviceModel = Backbone.Model.extend({
	urlRoot: 'api/device',
	idAttribute: 'objectid',
	deviceid: '',
	objectname: '',
	objectid: '',
	objectpswd: '',
	defaults: {
		'deviceid': '',
		'objectname': '',
		'objectid': null,
		'objectpswd': ''
	}
});
*/
/**
 * Device Backbone Model
 */
model.DeviceModel = Backbone.Model.extend({
	urlRoot: 'api/device',
	idAttribute: 'id',
	id: '',
	deviceid: '',
	objectname: '',
	objectid: '',
	objectpswd: '',
	defaults: {
		'id': null,
		'deviceid': '',
		'objectname': '',
		'objectid': '',
		'objectpswd': ''
	}
});


/**
 * Device Backbone Collection
 */
model.DeviceCollection = model.AbstractCollection.extend({
	url: 'api/devices',
	model: model.DeviceModel
});

/**
 * Report Backbone Model
 */
model.ReportModel = Backbone.Model.extend({
	urlRoot: 'api/report',
	idAttribute: 'id',
	id: '',
	objectid: '',
	name: '',
	friendlynameBg: '',
	friendlynameEn: '',
	href: '',
	sql: '',
	order: '',
	color: '',
	defaults: {
		'id': null,
		'objectid': '',
		'name': '',
		'friendlynameBg': '',
		'friendlynameEn': '',
		'href': '',
		'sql': '',
		'order': null,
		'color': ''
	}
});

/**
 * Report Backbone Collection
 */
model.ReportCollection = model.AbstractCollection.extend({
	url: 'api/reports',
	model: model.ReportModel
});

/**
 * RestIP Backbone Model
 */
model.RestIPModel = Backbone.Model.extend({
	urlRoot: 'api/restip',
	idAttribute: 'id',
	id: '',
	ip: '',
	comment: '',
	defaults: {
		'id': null,
		'ip': '',
		'comment': ''
	}
});

/**
 * RestIP Backbone Collection
 */
model.RestIPCollection = model.AbstractCollection.extend({
	url: 'api/restips',
	model: model.RestIPModel
});

/**
 * Setting Backbone Model
 */
model.SettingModel = Backbone.Model.extend({
	urlRoot: 'api/setting',
	idAttribute: 'name',
	name: '',
	value: '',
	defaults: {
		'name': null,
		'value': ''
	}
});

/**
 * Setting Backbone Collection
 */
model.SettingCollection = model.AbstractCollection.extend({
	url: 'api/settings',
	model: model.SettingModel
});

/**
 * Statistic Backbone Model
 */
model.StatisticModel = Backbone.Model.extend({
	urlRoot: 'api/statistic',
    idAttribute: 'id',
	id: '',
	opertype: '',
    operationType: '',    
	operid: '',
	datetime: '',
	description: '',
	defaults: {
		'id': null,
		'opertype': '',
		'operid': '',
		'datetime': '',
		'description': ''
	}
});

/**
 * Statistic Backbone Collection
 */
model.StatisticCollection = model.AbstractCollection.extend({
	url: 'api/statisticses',
	model: model.StatisticModel
});

/**
 * Subscription Backbone Model
 */
model.SubscriptionModel = Backbone.Model.extend({
	urlRoot: 'api/subscription',
	idAttribute: 'objectid',
	objectid: '',
	objectname: '',
	expiredate: '',
	customername: '',
	eik: '',
	address: '',
	hostname: '',
	active: '',
	defaults: {
		'objectid': null,
		'objectname': '',
		'expiredate': new Date(),
		'customername': '',
		'eik': '',
		'address': '',
		'hostname': '',
		'active': ''
	}
});

/**
 * Subscription Backbone Collection
 */
model.SubscriptionCollection = model.AbstractCollection.extend({
	url: 'api/subscriptions',
	model: model.SubscriptionModel
});

/**
 * User Backbone Model
 */
model.UserModel = Backbone.Model.extend({
	urlRoot: 'api/user',
	idAttribute: 'id',
	id: '',
	fullname: '',
	username: '',
	pswd: '',
	permissions: '',
	defaults: {
		'id': null,
		'fullname': '',
		'username': '',
		'pswd': '',
		'permissions': ''
	}
});

/**
 * User Backbone Collection
 */
model.UserCollection = model.AbstractCollection.extend({
	url: 'api/users',
	model: model.UserModel
});

/**
 * User Backbone Model
 */
model.UserModel = Backbone.Model.extend({
	urlRoot: 'api/user',
	idAttribute: 'id',
	id: '',
	roleId: '',
	username: '',
	password: '',
	firstName: '',
	lastName: '',
	defaults: {
		'id': null,
		'roleId': '',
		'username': '',
		'password': '',
		'firstName': '',
		'lastName': ''
	}
});

/**
 * User Backbone Collection
 */
model.UserCollection = model.AbstractCollection.extend({
	url: 'api/users',
	model: model.UserModel
});

