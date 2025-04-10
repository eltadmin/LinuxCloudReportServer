<?php
/*
------------------
Language: English
------------------
*/

$lang = array();
//common
$lang['SiteTitle'] = 'Detelina Report';
$lang['DETELINA'] = 'DETELINA';
$lang['reports'] = 'reports';
$lang['contacts'] = 'contacts';
$lang['OBJECTS'] = 'LOCATIONS';
$lang['btnExit'] = 'EXIT';
$lang['btnOperations'] = 'Operations';
$lang['btnPromotions'] = 'Promotions';
$lang['btnNewPromotion'] = 'New Promotion';
$lang['btnAvailability'] = 'Availability';
$lang['btnMonthlySales'] = 'Monthly Plu Sales';
$lang['btnDailySales'] = 'Daily Plu Sales';
$lang['btnSaveName'] = 'SAVE';
$lang['btnSavePrice'] = 'SAVE';
$lang['btnSaveBuyPrice'] = 'SAVE';
$lang['btnSaveEcrName'] = 'SAVE';
$lang['btnSaveBarcode'] = 'SAVE';
$lang['btnBack2'] = 'BACK';
$lang['btnSavePluDisabled'] = 'SAVE';

//Add object, rptlist
$lang['AddObjectTitle'] = 'add location';
$lang['ObjectName'] =  "location name";
$lang['ObjectID'] = "location id";
$lang['ObjectPswd'] = "location password";
$lang['btnAddObject'] = "ADD LOCATION";
$lang['infoAddObjectSeetings'] = 'Location settings could be found in "Detelina" options';
$lang['ObjectOperatorID'] = "operator name";
$lang['ObjectOperatorPswd'] = "operator password";
$lang['OptionalFields'] = "(operator name and operator password for DETELINA not required/mandatory)";

$lang['errObjectIDAlreadyExist'] = "Location with this ID already exist.";
$lang['errAllFieldsAreMandatory'] = "All fields are required!";
$lang["errObjectNotSubscribed"] = "Location is not registered.";
$lang["errObjectNotActive"] = "Location is not active and cannot be added.";
$lang["errObjectExpired"] = "Subscription for this location was expired on: ";
$lang["errObjOperatorNotValid"] = "Invalid operator details";
$lang["warnObjOperatorNotValid"] = "Success. The operator has no editing rights!";
$lang["objSavedOperatorInfo"] = "Success. Plu editing will be allowed after 12 hours.";
$lang["errEndTimeMustBeGreaterThanStartTime"] = "End time must be greater than start time.";


$lang["ObjectExpireOn"] = "expires on ";
$lang["btnDeleteObject"] = "Delete location";
$lang['DeleteObjectTitle'] = 'delete location';
$lang['confirmDeleteObject'] = 'Location will be deleted. Do you want to proceed?';
$lang["btnOk"] = "   Yes   ";
$lang["btnCancel"] = "   No   ";
$lang["btnBack"] = "   Back   ";

$lang["rptNoReceivedData"] = "No response from server.";
$lang["rptInvalidData"] = "Invalid data.";

$lang["rptRevenueTitle"] = "Current revenue";
$lang["rptMonthRevenue"] = "Monthly revenue";
$lang["rptDailyRevenue"] = "Daily revenue";
$lang["rptRevenueToDate"] = "To date: ";
$lang["rptRevenueLast5Days"] = "Revenue ";
$lang["rptRevenueChartLabel"] = "Revenue (leva)";
$lang["rptCurrency"] = "leva";
$lang["rptRevenuePreviousTurnover"] = "turnоver date ";
$lang["rptRevenueAvgTurnoverLabel"] = "Average";

$lang["rptGroupTurnoverTitle"] = "Daily groups turnover";
$lang["rptGroupPrint"] = "Daily Sales by Print Group";
$lang["rptMonthlyGroupPrint"] = "Monthly Sales by Print Group";
$lang["rptMonthlybyOperator"] = "Monthly Sales by Operators";
$lang["rptDailyByOperator"] = "Daily Sales by Operators";
$lang["rptGroupMonthlyTurnoverTitle"] = "Groups Monthly turnover";
$lang["rptGroupTurnoverToDate"] = "Date: ";
$lang["rptGroupTurnoverCurrentDate"] = "turnover for current day";

$lang["rptPluTurnoverTitle"] = "Most sold items - daily";
$lang["rptMonthPluTurnoverTitle"] = "Most sold items - monthly";
$lang["rptPluTurnoverToDate"] = "Date: ";
$lang["rptPluTurnoverCurrentDate"] = "amount of sales for the current day in ";

$lang["rptOpenbillsTitle"] = "Open bills";
$lang["rptOpenbillsToDate"] = "Date: ";
$lang["rptOpenbillsCount"] = "Open bills count: ";
$lang["rptOpenbillsSum"] = "Open bills amount: ";

$lang["rptClosedbillsTitle"] = "Closed bills";
$lang["rptClosedbillsCount"] = "Closed bills count: ";
$lang["rptClosedbillsSum"] = "Closed bills amount: ";

$lang["rptBillsHeader"] = " <thead><tr><th></th><th>No</th><th>Date</th><th>Operator</th><th>Amount</th></tr></thead>";
$lang["rptBillsInnerHeader"] = "   <thead><tr><th>Plu name</th><th>Qty.</th><th>Price</th><th>Total</th></tr></thead>";

//rpt void plues
$lang["rptVoidPluesHeader"] = "<thead><tr><th></th><th>Operator</th><th>Count</th><th>Amount</th></tr></thead>";
$lang["rptVoidPluesDetails"] = "<thead><tr><th>No</th><th>Date</th><th>Plu name</th><th>Qty.</th><th>Price</th><th>Total</th></tr></thead>";
$lang["rptVoidPluesTitle"] = "Void plues";
$lang["rptVoidPluesOpenBills"] = "open bills";
$lang["rptVoidPluesClosedBills"] = "closed bills";
$lang["rptVoidPluesToDate"] = "Date: ";
$lang["rptVoidPluesCount"] = "Void plues count: ";
$lang["rptVoidPluesSum"] = "Void plues total: ";
$lang["rptVoidPluesClosedDates"] = "Dates: ";

// rpt monthly expenses
$lang["rptMonthlyExpenses"] = "Consumables expenses";
$lang["rptMonthlyExpensesHeaderTHEAD"] = "<thead><tr><th></th><th>Article</th><th>Qty</th><th>Total</th></tr></thead>";
$lang["rptMonthlyExpensesDetailsTHEAD"] = "<thead><tr><th>Article</th><th>Timestamp</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>";
$lang["rptMonthlyStockExpenses"] = "Goods expenses";

$lang["objObjectId"] = "ID";
$lang["objDetails"] = "LOCATION DETAILS";
$lang["objEIK"] = "EIK";
$lang["objName"] = "Name";
$lang["objValidTo"] = "Valid to";
$lang["objViewName"] = "Location name";
$lang["objAddress"] = "Address";
$lang["objOldPassword"] = "Old password";
$lang["objNewPassword"] = "New password";
$lang["objNewPassword2"] = "New password";
$lang["objSave"] = "SAVE";
$lang["objDelete"] = "DELETE";
$lang["objSuccess"] = "Location saved";
$lang["objChPass"] = " Change password for location";
$lang["objEnter"] = "Enter";
$lang["objPassMatch"] = "Password does not match";
$lang["objPassLength"] = "Password must be at least 3 characters";
$lang["objOldPassErr"] = "Invalid password";
$lang['objTimeOffset'] = "Time offset (hours)";


//Articles
$lang['objArticles'] = "Plues";
$lang['objAricleNumber'] = "Plu №";
$lang['objArticleName'] = "Plu Name";
$lang['objGroupId'] = "Group ID";
$lang['objSellPrice'] = "Sell Price";
$lang['objPrice'] = "Price";
$lang['objPromotion'] = "Promotion";
$lang['objBarcode'] =   "Barcode";
$lang['objECRName'] = 'ECR Name';
$lang['objBuyPrice'] = 'Buy Price';
$lang['objTaxGroupID'] = 'Tax Group';
$lang["PluDisabled"] = 'Sale Disabled';

$lang['objPlueAvailability'] = 'Availability in stock';
$lang['objitemDetails'] = 'Plu Details'; 
$lang['objStorageName'] = 'Storage Name';
$lang['objStorageNumber'] = 'Storage №';
$lang['objQuantity'] = 'Quantity';
$lang['objTaxGroupDescr'] = 'Tax Group';
$lang['GroupDescr'] = 'Group';
$lang['StartDate'] = 'From (date)';
$lang['EndDate'] = 'To (date)';
$lang['totalSold'] = 'SOLD';
$lang['totalSum'] = 'TOTAL SUM';
$lang['PaymentCash'] = 'Cash payment';
$lang['PaymentCard'] = 'Card payment';
$lang["rptQuantity"] = 'Quantity';
$lang["rptCount"] = ' Count';
$lang["NoItemsFound"] = 'No items found.';
$lang['objTicketAval'] = 'Active Tickets';
$lang['objTicketControl'] = 'Tickets Control';
$lang['objOwner'] = 'Owner';

$lang['objActivePromotions'] = 'Active Promotions';
$lang['hasActivePromotions'] = ' has active promotion(s).';
$lang['objPromotionDetails'] = 'Promotion Details';
$lang['hasNoActivePromotions'] = ' has no active promotions.';
$lang['promotionalPrice'] = 'Promotional price';
$lang['promotionalDiscount'] = 'Promotional discount(%)';
$lang['priceAfterDiscount'] = 'Price after discount';
$lang['promotionalPrio'] = 'Priority';
$lang['fromTime'] = 'From (time):';
$lang['toTime'] = 'To (time):';
$lang['packetType'] = 'Type';
$lang['promotionType'] = 'Promotion type';
$lang['objPrice%'] = 'Price / %';

//Search Filter
$lang['SearchByName']   = 'By Name';
$lang['SearchByNumber'] = 'By Number';
$lang['SearchByPrice']  = 'By Price';
$lang['forPeriod'] = 'Period: ';
$lang['SearchByBarcode'] = 'By Barcode';



// BOS
$lang['rptDailyTurnoverByCounterparty'] = "Counterparty daily turnover";
$lang['rptMonthlyTurnoverByCounterparty'] = "Counterparty monthly turnover";


// Alert
$lang['AlertError'] = 'Error: ';
$lang['AlertWarning'] = 'Warning: ';

//TCP server errors
/*
$lang['C_HttpErr_MissingClientID'] = 'Location not found or missing.'; // 100;
$lang['C_HttpErr_MissingClientPass'] = 'Missing location password.';   // 101;
$lang['C_HttpErr_MissingLoginInfo'] = 'MIssing authentication info.';  // 102;
$lang['C_HttpErr_LoginIncorrect'] = 'Invalid username or password.';   // 103;
$lang['C_HttpErr_ClientIsOffline'] = 'Location not active.';           // 200;
$lang['C_HttpErr_ClientIsByssy'] = 'System busy. Please try again.';   // 201;
$lang['C_HttpErr_NotDefined'] = 'System error.';                       // other;
*/

$lang['100'] = 'Location not found or missing.'; // C_HttpErr_MissingClientID       100     в http заявката липсва ID на клиентската база данни
$lang['102'] = 'Missing authentication info.'; // C_HttpErr_MissingLoginInfo      102    в http заявката липсва логин информация (user, pass)
$lang['103'] = 'Invalid username or password.'; // C_HttpErr_LoginIncorrect        103    неуспешен логин на ниво http сървър (акаунтите се описват в ini файла на сървъра)
$lang['200'] = 'Location is offline.'; // C_HttpErr_ClientIsOffline       200    Търсения клиент не е онлайн (активен)
$lang['201'] = 'System busy. Please try again.'; // C_HttpErr_ClientIsByssy         201    Търсения килент в момента изпълнява заявка и е зает (опитай по-късно)
$lang['202'] = 'Duplicated client.'; // C_HttpErr_ClientDuplicate       202    Търсения клиент има дублиран. Тоест има повече от един клиент с това ID логнат към системата. (По принцип вероятността за такава ситуация е нищожна)
$lang['203'] = 'No response from client.'; // C_HttpErr_ClientNotRespond      203    Клиента не отговаря на изпратената заявка в опрделеното време (Таймаут)(таймауте е 30 секунди)
$lang['204'] = 'Request send fail.'; // C_HttpErr_ClientFailSend        204    Неуспешно изпращане на заявка към клиента. Явно връзката се е разпаднала в последния момент.
$lang['205'] = 'Unknown document type.'; // C_HttpErr_DocumentUnknown       205    http заявката съдържа невалидно име на документ
$lang['1000'] = 'Client system error.'; // C_ErrCode_DP_Unknown    1000    Неочаквана системна грешка
$lang['1001'] = 'Decrypt fail.'; // C_ErrCode_DP_Decrypt    1001    Неуспешно декриптиране на данните
$lang['1002'] = 'Wrong message format.'; // C_ErrCode_DP_JsonErr    1002    Невалиден JSON формат на съобщението/заявката
$lang['1003'] = 'Client not found or missing.'; // C_ErrCode_DP_WrongId    1003    Невалидно ID на клиентска база данни (не би трябвало да се случва при коректни заявки)
$lang['1004'] = 'Invalid client username or password.'; // C_ErrCode_DP_WrongPass    1004    Неуспешен логин. Паролата на базата данни не съвпада
$lang['1005'] = 'Invalid data message format.'; // C_ErrCode_DP_WrongQuery    1005    Невалиден формат на съобщението. Липсва SQL заявка в таг от тип „Query”
$lang['1006'] = 'Invaid command message format.'; // C_ErrCode_DP_WrongCmd    1006    Невалиден формат на съобщението. Липсва parameter в таг  от тип „Command”
$lang['1007'] = 'Database operation failed.'; // C_ErrCode_DP_FailSqlExc    1007    Неуспешно изпълнение на SQL заявката. Грешка от фиребирд сървъра
$lang['1008'] = 'Result message failed.'; // C_ErrCode_DP_FailSqlJsn    1008    Неуспешно конвертиране на дейтасета в JSON масив
$lang['1009'] = 'Cammand execution failed'; // C_ErrCode_DP_FailCommand    1009    Неуспешно изпълнение на командата
$lang['1010'] = 'Invalid request format.'; // C_ErrCode_DP_JsonEmpty          1010       Празна заявка (не съдържа “Query” или “Command”)
$lang['1011'] = 'Susbcription expired.'; // C_ErrCode_DP_SvcExpired          1011       Имаме изтекъл абонамент
$lang['1020'] = 'Communucaion error. Please try again.'; //C_ErrCode_DP_CommError         1020       Грешка при комуникация. Моля опитайте пак.
$lang['1021'] = 'The client is not connected. Please ensure the client is connected and try again.'; //C_ErrCode_DP_CommError         1020       Грешка при комуникация. Моля опитайте пак.
$lang['C_HttpErr_NotDefined'] = 'System error.';                             // other;

?>