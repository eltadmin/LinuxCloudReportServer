<?php
/*
-------------------
Language: Bulgarian
-------------------
*/

$lang = array();
//common
$lang['SiteTitle'] = 'Detelina Report';
$lang['DETELINA'] = 'ДЕТЕЛИНА';
$lang['reports'] = 'справки';
$lang['contacts'] = 'контакти';
$lang['OBJECTS'] = 'ОБЕКТИ';
$lang['btnExit'] = 'ИЗХОД';
$lang['btnOperations'] = 'ОПЕРАЦИИ';
$lang['btnPromotions'] = 'ПРОМОЦИИ';
$lang['btnNewPromotion'] = 'НОВА ПРОМОЦИЯ';
$lang['btnAvailability'] = 'НАЛИЧНОСТ';
$lang['btnMonthlySales'] = 'МЕСЕЧНИ ПРОДАЖБИ ПО АРТИКУЛ';
$lang['btnDailySales'] = 'ДНЕВНИ ПРОДАЖБИ ПО АРТИКУЛ';
$lang['btnSaveName'] = 'ЗАПИС';
$lang['btnSavePrice'] = 'ЗАПИС';
$lang['btnSaveBuyPrice'] = 'ЗАПИС';
$lang['btnSaveEcrName'] = 'ЗАПИС';
$lang['btnSaveBarcode'] = 'ЗАПИС';
$lang['btnSavePluDisabled'] = 'ЗАПИС';

$lang['btnBack2'] = 'НАЗАД';


//Add object, rptlist
$lang['AddObjectTitle'] = 'добавяне на обект';
$lang['ObjectName'] =  "обект име";
$lang['ObjectID'] = "обект id";
$lang['ObjectPswd'] = "обект парола";
$lang['btnAddObject'] = "ДОБАВЯНЕ";
$lang['infoAddObjectSeetings'] = 'Данните за обекта могат да бъдат намерени в "Детелина" настройки';
$lang['ObjectOperatorID'] = "оператор име";
$lang['ObjectOperatorPswd'] = "оператор парола";
$lang['OptionalFields'] = "(оператор име и оператор парола за ДЕТЕЛИНА не са задължителни)";

$lang['errObjectIDAlreadyExist'] = "Обект със същото ID вече съществува.";
$lang['errAllFieldsAreMandatory'] = "Всички полета са задължителни!";
$lang["errObjectNotSubscribed"] = "Обектът не е регистриран.";
$lang["errObjectNotActive"] = "Обектът не е активен и не може да бъде добавен.";
$lang["errObjectExpired"] = "Абонаментът за този обект е изтекъл на: "; 
$lang["errObjOperatorNotValid"] = "Грешни данни за оператор";
$lang["warnObjOperatorNotValid"] = "Успешен запис. Операторът няма права за редакция!";
$lang["objSavedOperatorInfo"] = "Успешен запис. Редакцията на артикул ще е позволена след 12 астрономически часа.";
$lang["errEndTimeMustBeGreaterThanStartTime"] = "Крайният час не може да бъде по-малък от началния.";



$lang["ObjectExpireOn"] = "валиден до ";
$lang['DeleteObjectTitle'] = 'изтриване на обекта';
$lang['confirmDeleteObject'] = 'Обектът ще бъде изтрит. Желаете ли да продължите?';
$lang["btnDeleteObject"] = "Изтриване обекта";
$lang["btnOk"] = "   Да   ";
$lang["btnCancel"] = "   Не   ";
$lang["btnBack"] = "   Връщане   ";

$lang["rptNoReceivedData"] = "Няма получени данни.";
$lang["rptInvalidData"] = "Нeвалидни данни.";

$lang["rptRevenueTitle"] = "Дневен оборот";
$lang["rptMonthRevenue"] = "Месечен оборот";
$lang["rptDailyRevenue"] = "Дневен оборот";
$lang["rptRevenueToDate"] = "Към дата: ";
$lang["rptRevenueLast5Days"] = "Оборот ";
$lang["rptRevenueChartLabel"] = "Оборот (лв)";
$lang["rptCurrency"] = "лв.";
$lang["rptRevenuePreviousTurnover"] = "оборот за дата ";
$lang["rptRevenueAvgTurnoverLabel"] = "Средно";

$lang["rptBillsHeader"] = " <thead><tr><th></th><th>No</th><th>Дата</th><th>Оператор</th><th>Сума</th></tr></thead>";
$lang["rptBillsInnerHeader"] = "   <thead><tr><th>Артикул име</th><th>Кол.</th><th>ед.цена</th><th>сума</th></tr></thead>";

$lang["rptGroupTurnoverTitle"] = "Дневен оборот стокови групи";
$lang["rptGroupPrint"] = "Дневни продажби по групи за печат";
$lang["rptMonthlyGroupPrint"] = "Месечни продажби по групи за печат";
$lang["rptMonthlybyOperator"] = "Месечни продажби по оператори";
$lang["rptDailyByOperator"] = "Дневни продажби по оператори";
$lang["rptGroupMonthlyTurnoverTitle"] = "Месечен оборот стокови групи";
$lang["rptGroupTurnoverToDate"] = "Към дата: ";
$lang["rptGroupTurnoverCurrentDate"] = "оборот за текущия ден";

$lang["rptPluTurnoverTitle"] = "Най-продавани артикули - дневно";
$lang["rptMonthPluTurnoverTitle"] = "Най-продавани артикули - месечно";
$lang["rptPluTurnoverToDate"] = "Към дата: ";
$lang["rptPluTurnoverCurrentDate"] = "сума продажби за текущия ден в ";

$lang["rptOpenbillsTitle"] = "Отворени сметки";
$lang["rptOpenbillsToDate"] = "Към дата: ";
$lang["rptOpenbillsCount"] = "Брой отворени сметки: ";
$lang["rptOpenbillsSum"] = "Сума отворени сметки: ";

$lang["rptClosedbillsTitle"] = "Закрити сметки";
$lang["rptClosedbillsCount"] = "Брой закрити сметки: ";
$lang["rptClosedbillsSum"] = "Сума закрити сметки: ";

//rpt void plues
$lang["rptVoidPluesHeader"] = "<thead><tr><th></th><th>Оператор</th><th>Брой</th><th>Сума</th></tr></thead>";
$lang["rptVoidPluesDetails"] = "<thead><tr><th>No</th><th>Дата</th><th>Артикул име</th><th>Кол.</th><th>ед.цена</th><th>сума</th></tr></thead>";
$lang["rptVoidPluesTitle"] = "Сторнирани артикули";
$lang["rptVoidPluesOpenBills"] = "неприключени сметки";
$lang["rptVoidPluesClosedBills"] = "приключени сметки";
$lang["rptVoidPluesToDate"] = "Към дата: ";
$lang["rptVoidPluesCount"] = "Брой сторнирани артикули: ";
$lang["rptVoidPluesSum"] = "Сума сторнирани артикули: ";
$lang["rptVoidPluesClosedDates"] = "За период: ";

// rpt monthly expenses
$lang["rptMonthlyExpenses"] = "Разходи консумативи";
$lang["rptMonthlyExpensesHeaderTHEAD"] = "<thead><tr><th></th><th>Артикул</th><th>К-во</th><th>Общо</th></tr></thead>";
$lang["rptMonthlyExpensesDetailsTHEAD"] = "<thead><tr><th>Артикул</th><th>Дата и час</th><th>К-во</th><th>Цена</th><th>Общо</th></tr></thead>";
$lang["rptMonthlyStockExpenses"] = "Разходи Стоки";

$lang["objObjectId"] = "ID";
$lang["objDetails"] = "ДАННИ ЗА ОБЕКТА";
$lang["objDetails2"] = "РЕДАКЦИЯ НА АРТИКУЛ";
$lang["objDetails3"] = "МЕСЕЧЕН ОБОРОТ";
$lang["objDetails4"] = "ДНЕВЕН ОБОРОТ СТОКОВИ ГРУПИ";
$lang["objEIK"] = "ЕИК";
$lang["objName"] = "Име";
$lang["objValidTo"] = "Валиден до";
$lang["objViewName"] = "Име на обект";
$lang["objAddress"] = "Адрес";
$lang["objOldPassword"] = "Стара парола";
$lang["objNewPassword"] = "Нова парола";
$lang["objNewPassword2"] = "Нова парола";
$lang["objSave"] = "ЗАПИС";
$lang["objDelete"] = "ИЗТРИВАНЕ";
$lang["objSuccess"] = "Успешен запис!";
$lang["objChPass"] = "Смяна на паролата на обект";
$lang["objEnter"] = "Въведете ";
$lang["objPassMatch"] = "Паролата не съвпада";
$lang["objPassLength"] = "Паролата трябва да бъде поне 3 символа";
$lang["objOldPassErr"] = "Грешна парола";
$lang['objTimeOffset'] = "Изместване (часа)";

//Articles
$lang['objArticles'] = "Артикули";
$lang['objAricleNumber'] = "Арт. №";
$lang['objArticleName'] = "Име на артикул";
$lang['objName2'] = "Име";
$lang['objGroupId'] = "№ група";
$lang['objSellPrice'] = "Продажна цена";
$lang['objPrice'] = "Цена";
$lang['objPromotion'] = "Промоция";
$lang['objBarcode'] =   "Баркод";
$lang['objECRName'] = 'Касово име';
$lang['objBuyPrice'] = 'Покупна цена';
$lang['objTaxGroupID'] = 'Дан. група';
$lang['objPlueAvailability'] = 'Наличност в склад';
$lang['objTicketAval'] = 'Активни билети';
$lang['objTicketControl'] = 'Контрол билети';
$lang['objitemDetails'] = 'Детайли за артикул'; 
$lang['objStorageName'] = 'Склад Име';
$lang['objStorageNumber'] = 'Склад №';
$lang['objQuantity'] = 'Количество';
$lang['objTaxGroupDescr'] = 'Дан. група';
$lang['GroupDescr'] = 'Група';
$lang['StartDate'] = 'Начална Дата';
$lang['EndDate'] = 'Крайна Дата';
$lang['totalSold'] = 'ПРОДАДЕНИ';
$lang['totalSum'] = 'ОБЩА СУМА';
$lang['forPeriod'] = 'За период: ';
$lang['PaymentCash'] = 'Платени в брой';
$lang['PaymentCard'] = 'Платени с карта';
$lang["rptQuantity"] = 'Количество';
$lang["rptCount"] = 'Брой';
$lang["PluDisabled"] = 'Забранена прод. ';
$lang["NoItemsFound"] = 'Не бяха открити артикули.';
$lang["objOwner"] = 'Група';


$lang['objActivePromotions'] = 'Активни промоции';
$lang['hasActivePromotions'] = ' има активни промоции.';
$lang['objPromotionDetails'] = 'Детайли за промоция';
$lang['hasNoActivePromotions'] = ' няма активни промоции.';
$lang['promotionalPrice'] = 'Промоционална цена';
$lang['promotionalDiscount'] = 'Промоционална отстъпка(%)';
$lang['priceAfterDiscount'] = 'Цена след отстъпка';
$lang['promotionalPrio'] = 'Приоритет';
$lang['hasNoActivePromotions'] = ' няма активни промоции.';
$lang['fromTime'] = 'От (час):';
$lang['toTime'] = 'До (час):';
$lang['packetType'] = 'Тип';
$lang['promotionType'] = 'Вид промоция';
$lang['objPrice%'] = 'Цена / %';

//Search Filter
$lang['SearchByName']   = 'По име';
$lang['SearchByNumber'] = 'По номер';
$lang['SearchByPrice']  = 'По цена';
$lang['SearchByBarcode'] = 'По баркод';




// BOS
$lang['rptDailyTurnoverByCounterparty'] = "Дневен оборот по контрагенти";
$lang['rptMonthlyTurnoverByCounterparty'] = "Месечен оборот по контрагенти";

// Alert
$lang['AlertError'] = 'Грешка: ';
$lang['AlertWarning'] = 'Съобщение: ';

//TCP server errors
/*
$lang['C_HttpErr_MissingClientID'] = 'Обекта не е намерен или е не е активен.'; // 100;
$lang['C_HttpErr_MissingClientPass'] = 'Липсва парола за обект.';               // 101;
$lang['C_HttpErr_MissingLoginInfo'] = 'Липсва информаци за автентикация.';      // 102;
$lang['C_HttpErr_LoginIncorrect'] = 'Невалидни потебител или парола.';          // 103;
$lang['C_HttpErr_ClientIsOffline'] = 'Обекта не е активен';                     // 200;
$lang['C_HttpErr_ClientIsByssy'] = 'Клиента е зает. Моля опитайте пак.';        // 201;
*/

$lang['100'] = 'Липсват данни за обект.'; // C_HttpErr_MissingClientID       100     в http заявката липсва ID на клиентската база данни
$lang['102'] = 'Липсва информация за автентикация.'; // C_HttpErr_MissingLoginInfo      102    в http заявката липсва логин информация (user, pass)
$lang['103'] = 'Невалидни потебител или парола.'; // C_HttpErr_LoginIncorrect        103    неуспешен логин на ниво http сървър (акаунтите се описват в ini файла на сървъра)
$lang['200'] = 'Обектът не е активен.'; // C_HttpErr_ClientIsOffline       200    Търсения клиент не е онлайн (активен)
$lang['201'] = 'Сървърът е зает. Моля опитайте пак.'; // C_HttpErr_ClientIsByssy         201    Търсения килент в момента изпълнява заявка и е зает (опитай по-късно)
$lang['202'] = 'Дублиран клиент.'; // C_HttpErr_ClientDuplicate       202    Търсения клиент има дублиран. Тоест има повече от един клиент с това ID логнат към системата. (По принцип вероятността за такава ситуация е нищожна)
$lang['203'] = 'Няма отговор.'; // C_HttpErr_ClientNotRespond      203    Клиента не отговаря на изпратената заявка в опрделеното време (Таймаут)(таймауте е 30 секунди)
$lang['204'] = 'Неуспешно изпращане на заявка.'; // C_HttpErr_ClientFailSend        204    Неуспешно изпращане на заявка към клиента. Явно връзката се е разпаднала в последния момент.
$lang['205'] = 'Невалидно име на документ.'; // C_HttpErr_DocumentUnknown       205    http заявката съдържа невалидно име на документ
$lang['1000'] = 'Системна грешка клиент.'; // C_ErrCode_DP_Unknown    1000    Неочаквана системна грешка
$lang['1001'] = 'Неуспешно декриптиране на данните.'; // C_ErrCode_DP_Decrypt    1001    Неуспешно декриптиране на данните
$lang['1002'] = 'Невалиден формат на съобщението.'; // C_ErrCode_DP_JsonErr    1002    Невалиден JSON формат на съобщението/заявката
$lang['1003'] = 'Обектът не е намерен или не е активен.'; // C_ErrCode_DP_WrongId    1003    Невалидно ID на клиентска база данни (не би трябвало да се случва при коректни заявки)
$lang['1004'] = 'Неуспешен логин. Паролата на базата данни не съвпада.'; // C_ErrCode_DP_WrongPass    1004    Неуспешен логин. Паролата на базата данни не съвпада
$lang['1005'] = 'Невалиден формат на съобщението.'; // C_ErrCode_DP_WrongQuery    1005    Невалиден формат на съобщението. Липсва SQL заявка в таг от тип „Query”
$lang['1006'] = 'Невалиден формат на команда.'; // C_ErrCode_DP_WrongCmd    1006    Невалиден формат на съобщението. Липсва parameter в таг  от тип „Command”
$lang['1007'] = 'Грешка изпълнение заявка.'; // C_ErrCode_DP_FailSqlExc    1007    Неуспешно изпълнение на SQL заявката. Грешка от фиребирд сървъра
$lang['1008'] = 'Неуспешно конвертиране на съобщението.'; // C_ErrCode_DP_FailSqlJsn    1008    Неуспешно конвертиране на дейтасета в JSON масив
$lang['1009'] = 'Неуспешно изпълнение на команда.'; // C_ErrCode_DP_FailCommand    1009    Неуспешно изпълнение на командата
$lang['1010'] = 'Невалиден формат на заявка.'; // C_ErrCode_DP_JsonEmpty          1010       Празна заявка (не съдържа “Query” или “Command”)
$lang['1011'] = 'Изтекъл абонамент.'; // C_ErrCode_DP_SvcExpired          1011       Имаме изтекъл абонамент
$lang['1020'] = 'Грешка при комуникация. Моля опитайте пак.'; //C_ErrCode_DP_CommError         1020       Грешка при комуникация. Моля опитайте пак.
$lang['1021'] = 'Клиентът не е свързан. Моля, уверете се, че клиентът е свързан и опитайте отново.'; //C_ErrCode_DP_CommError         1020       Грешка при комуникация. Моля опитайте пак.
$lang['C_HttpErr_NotDefined'] = 'Системна грешка.';                             // other;
?>