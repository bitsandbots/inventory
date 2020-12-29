<?php
/**
 * includes/formatcurrency.php
 *
 * @package default
 * @param unknown $floatcurr
 * @param unknown $curr      (optional)
 * @return unknown
 */


function formatcurrency($floatcurr, $curr = 'USD') {

	/**
	 * A list of the ISO 4217 currency codes with symbol,format and symbol order
	 *
	 * Symbols from
	 * http://character-code.com/currency-html-codes.php
	 * http://www.phpclasses.org/browse/file/2054.html
	 * https://github.com/yiisoft/yii/blob/633e54866d54bf780691baaaa4a1f847e8a07e23/framework/i18n/data/en_us.php
	 *
	 * Formats from
	 * http://www.joelpeterson.com/blog/2011/03/formatting-over-100-currencies-in-php/
	 *
	 * Array with key as ISO 4217 currency code
	 * 0 - Currency Symbol if there's
	 * 1 - Round
	 * 2 - Thousands separator
	 * 3 - Decimal separator
	 * 4 - 0 = symbol in front OR 1 = symbol after currency
	 */
	$currencies = array(
		'ARS' => array(NULL, 2, ',', '.', 0),          //  Argentine Peso
		'AMD' => array(NULL, 2, '.', ',', 0),          //  Armenian Dram
		'AWG' => array(NULL, 2, '.', ',', 0),          //  Aruban Guilder
		'AUD' => array('AU$', 2, '.', ' ', 0),          //  Australian Dollar
		'BSD' => array(NULL, 2, '.', ',', 0),          //  Bahamian Dollar
		'BHD' => array(NULL, 3, '.', ',', 0),          //  Bahraini Dinar
		'BDT' => array(NULL, 2, '.', ',', 0),          //  Bangladesh, Taka
		'BZD' => array(NULL, 2, '.', ',', 0),          //  Belize Dollar
		'BMD' => array(NULL, 2, '.', ',', 0),          //  Bermudian Dollar
		'BOB' => array(NULL, 2, '.', ',', 0),          //  Bolivia, Boliviano
		'BAM' => array(NULL, 2, '.', ',', 0),          //  Bosnia and Herzegovina, Convertible Marks
		'BWP' => array(NULL, 2, '.', ',', 0),          //  Botswana, Pula
		'BRL' => array('R$', 2, ',', '.', 0),          //  Brazilian Real
		'BND' => array(NULL, 2, '.', ',', 0),          //  Brunei Dollar
		'CAD' => array('CA$', 2, '.', ',', 0),          //  Canadian Dollar
		'KYD' => array(NULL, 2, '.', ',', 0),          //  Cayman Islands Dollar
		'CLP' => array(NULL, 0, '', '.', 0),           //  Chilean Peso
		'CNY' => array('CN&yen;', 2, '.', ',', 0),          //  China Yuan Renminbi
		'COP' => array(NULL, 2, ',', '.', 0),          //  Colombian Peso
		'CRC' => array(NULL, 2, ',', '.', 0),          //  Costa Rican Colon
		'HRK' => array(NULL, 2, ',', '.', 0),          //  Croatian Kuna
		'CUC' => array(NULL, 2, '.', ',', 0),          //  Cuban Convertible Peso
		'CUP' => array(NULL, 2, '.', ',', 0),          //  Cuban Peso
		'CYP' => array(NULL, 2, '.', ',', 0),          //  Cyprus Pound
		'CZK' => array('Kc', 2, '.', ',', 1),          //  Czech Koruna
		'DKK' => array(NULL, 2, ',', '.', 0),          //  Danish Krone
		'DOP' => array(NULL, 2, '.', ',', 0),          //  Dominican Peso
		'XCD' => array('EC$', 2, '.', ',', 0),          //  East Caribbean Dollar
		'EGP' => array(NULL, 2, '.', ',', 0),          //  Egyptian Pound
		'SVC' => array(NULL, 2, '.', ',', 0),          //  El Salvador Colon
		'EUR' => array('&euro;', 2, ',', '.', 0),          //  Euro
		'GHC' => array(NULL, 2, '.', ',', 0),          //  Ghana, Cedi
		'GIP' => array(NULL, 2, '.', ',', 0),          //  Gibraltar Pound
		'GTQ' => array(NULL, 2, '.', ',', 0),          //  Guatemala, Quetzal
		'HNL' => array(NULL, 2, '.', ',', 0),          //  Honduras, Lempira
		'HKD' => array('HK$', 2, '.', ',', 0),          //  Hong Kong Dollar
		'HUF' => array('HK$', 0, '', '.', 0),           //  Hungary, Forint
		'ISK' => array('kr', 0, '', '.', 1),           //  Iceland Krona
		'INR' => array('&#2352;', 2, '.', ',', 0),          //  Indian Rupee ₹
		'IDR' => array(NULL, 2, ',', '.', 0),          //  Indonesia, Rupiah
		'IRR' => array(NULL, 2, '.', ',', 0),          //  Iranian Rial
		'JMD' => array(NULL, 2, '.', ',', 0),          //  Jamaican Dollar
		'JPY' => array('&yen;', 0, '', ',', 0),           //  Japan, Yen
		'JOD' => array(NULL, 3, '.', ',', 0),          //  Jordanian Dinar
		'KES' => array(NULL, 2, '.', ',', 0),          //  Kenyan Shilling
		'KWD' => array(NULL, 3, '.', ',', 0),          //  Kuwaiti Dinar
		'LVL' => array(NULL, 2, '.', ',', 0),          //  Latvian Lats
		'LBP' => array(NULL, 0, '', ' ', 0),           //  Lebanese Pound
		'LTL' => array('Lt', 2, ',', ' ', 1),          //  Lithuanian Litas
		'MKD' => array(NULL, 2, '.', ',', 0),          //  Macedonia, Denar
		'MYR' => array(NULL, 2, '.', ',', 0),          //  Malaysian Ringgit
		'MTL' => array(NULL, 2, '.', ',', 0),          //  Maltese Lira
		'MUR' => array(NULL, 0, '', ',', 0),           //  Mauritius Rupee
		'MXN' => array('MX$', 2, '.', ',', 0),          //  Mexican Peso
		'MZM' => array(NULL, 2, ',', '.', 0),          //  Mozambique Metical
		'NPR' => array(NULL, 2, '.', ',', 0),          //  Nepalese Rupee
		'ANG' => array(NULL, 2, '.', ',', 0),          //  Netherlands Antillian Guilder
		'ILS' => array('&#8362;', 2, '.', ',', 0),          //  New Israeli Shekel ₪
		'TRY' => array(NULL, 2, '.', ',', 0),          //  New Turkish Lira
		'NZD' => array('NZ$', 2, '.', ',', 0),          //  New Zealand Dollar
		'NOK' => array('kr', 2, ',', '.', 1),          //  Norwegian Krone
		'PKR' => array(NULL, 2, '.', ',', 0),          //  Pakistan Rupee
		'PEN' => array(NULL, 2, '.', ',', 0),          //  Peru, Nuevo Sol
		'UYU' => array(NULL, 2, ',', '.', 0),          //  Peso Uruguayo
		'PHP' => array(NULL, 2, '.', ',', 0),          //  Philippine Peso
		'PLN' => array(NULL, 2, '.', ' ', 0),          //  Poland, Zloty
		'GBP' => array('&pound;', 2, '.', ',', 0),          //  Pound Sterling
		'OMR' => array(NULL, 3, '.', ',', 0),          //  Rial Omani
		'RON' => array(NULL, 2, ',', '.', 0),          //  Romania, New Leu
		'ROL' => array(NULL, 2, ',', '.', 0),          //  Romania, Old Leu
		'RUB' => array(NULL, 2, ',', '.', 0),          //  Russian Ruble
		'SAR' => array(NULL, 2, '.', ',', 0),          //  Saudi Riyal
		'SGD' => array(NULL, 2, '.', ',', 0),          //  Singapore Dollar
		'SKK' => array(NULL, 2, ',', ' ', 0),          //  Slovak Koruna
		'SIT' => array(NULL, 2, ',', '.', 0),          //  Slovenia, Tolar
		'ZAR' => array('R', 2, '.', ' ', 0),          //  South Africa, Rand
		'KRW' => array('&#8361;', 0, '', ',', 0),           //  South Korea, Won ₩
		'SZL' => array(NULL, 2, '.', ', ', 0),         //  Swaziland, Lilangeni
		'SEK' => array('kr', 2, ',', '.', 1),          //  Swedish Krona
		'CHF' => array('SFr ', 2, '.', '\'', 0),         //  Swiss Franc
		'TZS' => array(NULL, 2, '.', ',', 0),          //  Tanzanian Shilling
		'THB' => array('&#3647;', 2, '.', ',', 1),          //  Thailand, Baht ฿
		'TOP' => array(NULL, 2, '.', ',', 0),          //  Tonga, Paanga
		'AED' => array(NULL, 2, '.', ',', 0),          //  UAE Dirham
		'UAH' => array(NULL, 2, ',', ' ', 0),          //  Ukraine, Hryvnia
		'USD' => array('$', 2, '.', ',', 0),          //  US Dollar
		'VUV' => array(NULL, 0, '', ',', 0),           //  Vanuatu, Vatu
		'VEF' => array(NULL, 2, ',', '.', 0),          //  Venezuela Bolivares Fuertes
		'VEB' => array(NULL, 2, ',', '.', 0),          //  Venezuela, Bolivar
		'VND' => array('&#x20ab;', 0, '', '.', 0),           //  Viet Nam, Dong ₫
		'ZWD' => array(NULL, 2, '.', ' ', 0),          //  Zimbabwe Dollar
	);


	//rupees weird format
	if ($curr == "INR")
		$number = formatinr($floatcurr);
	else
		$number = number_format($floatcurr, $currencies[$curr][1], $currencies[$curr][2], $currencies[$curr][3]);

	//adding the symbol in the back
	if ($currencies[$curr][0] === NULL)
		$number.= ' '.$curr;
	elseif ($currencies[$curr][4]===1)
		$number.= $currencies[$curr][0];
	//normally in front
	else
		$number = $currencies[$curr][0].$number;

	return $number;
}


/**
 * formats to indians rupees
 * from http://www.joelpeterson.com/blog/2011/03/formatting-over-100-currencies-in-php/
 *
 * @param float   $input money
 * @return string        formated currency
 */
function formatinr($input) {
	//CUSTOM FUNCTION TO GENERATE ##,##,###.##
	$dec = "";
	$pos = strpos($input, ".");
	if ($pos === false) {
		//no decimals
	} else {
		//decimals
		$dec = substr(round(substr($input, $pos), 2), 1);
		$input = substr($input, 0, $pos);
	}
	$num = substr($input, -3); //get the last 3 digits
	$input = substr($input, 0, -3); //omit the last 3 digits already stored in $num
	while (strlen($input) > 0) //loop the process - further get digits 2 by 2
		{
		$num = substr($input, -2).",".$num;
		$input = substr($input, 0, -2);
	}
	return $num . $dec;
}


/*
    echo '<br>'.formatcurrency(39174.00000000001);             //1,000,045.25 (USD)
    echo '<br>'.formatcurrency(1000045.25, "CHF");        //1'000'045.25
    echo '<br>'.formatcurrency(1000045.25, "EUR");      //1.000.045,25
    echo '<br>'.formatcurrency(1000045, "JPY");         //1,000,045
    echo '<br>'.formatcurrency(1000045, "VND");         //1 000 045
    echo '<br>'.formatcurrency(1000045.25, "INR");      //10,00,045.25
    echo '<br>'.formatcurrency(1000045.25, "ILS");      //10,00,045.25
    echo '<br>'.formatcurrency(1000045.25, "THB");      //10,00,045.25
    echo '<br>'.formatcurrency(1000045.25, "KRW");      //10,00,045.25
*/
