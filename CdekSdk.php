<?php

namespace box2box\cdek;

use Exception;
use LSS\XML2Array;
use LSS\Array2XML;
use Guzzle\Http\Client;

class CdekSdk
{
    const URL_DELIVERY_REQUEST_MAIN = "http://gw.edostavka.ru:11443/new_orders.php";
    const URL_CALL_COURIER_MAIN     = "http://gw.edostavka.ru:11443/call_courier.php";
    const URL_STATUS_REPORT_MAIN    = "http://gw.edostavka.ru:11443/status_report_h.php";
    const URL_PVZ_LIST_MAIN         = "http://gw.edostavka.ru:11443/pvzlist.php";
    const URL_INFO_REQUEST_MAIN     = "http://gw.edostavka.ru:11443/info_report.php";
    const URL_DELETE_ORDERS_MAIN    = "http://gw.edostavka.ru:11443/delete_orders.php";

    const URL_DELIVERY_REQUEST_RESERVE = "http://lk.cdek.ru:11443/new_orders.php";
    const URL_CALL_COURIER_RESERVE     = "http://lk.cdek.ru:11443/call_courier.php";
    const URL_STATUS_REPORT_RESERVE    = "http://lk.cdek.ru:11443/status_report_h.php";
    const URL_INFO_REQUEST_RESERVE     = "http://lk.cdek.ru:11443/info_report.php";
    const URL_PVZ_LIST_RESERVE         = "http://lk.cdek.ru:11443/pvzlist.php";

    const STATUS_CREATED                          = 1;
    const STATUS_DELETED                          = 2;
    const STATUS_TAKEN_TO_SENDER_STORAGE          = 3;
    const STATUS_SENT_FROM_SENDER_CITY            = 6;
    const STATUS_RETURNED_TO_SENDER_STORAGE       = 16;
    const STATUS_GIVEN_TO_CARRIER_IN_SENDER_CITY  = 7;
    const STATUS_SENT_TO_TRANSIT_CITY             = 21;
    const STATUS_MET_IN_TRANSIT_CITY              = 22;
    const STATUS_TAKEN_TO_TRANSIT_STORAGE         = 13;
    const STATUS_RETURNED_TO_TRANSIT_STORAGE      = 17;
    const STATUS_SENT_FROM_TRANSIT_CITY           = 19;
    const STATUS_GIVEN_TO_CARRIER_IN_TRANSIT_CITY = 20;
    const STATUS_SENT_TO_RECIPIENT_CITY           = 8;
    const STATUS_MET_IN_RECIPIENT_CITY            = 9;
    const STATUS_TAKEN_TO_DELIVERY_STORAGE        = 10;
    const STATUS_TAKEN_TO_STORAGE_TILL_DEMAND     = 12;
    const STATUS_GIVEN_FOR_DELIVERY               = 11;
    const STATUS_RETURNED_TO_DELIVERY_STORAGE     = 18;
    const STATUS_HANDED                           = 4;
    const STATUS_NOT_HANDED                       = 5;

    private $account;

    private $securePassword;

    private $decodeXml;

    /**
     * @param $account        - учетная запись. Учетная запись для интеграции не совпадает с учетной записью доступа в Личный Кабинет
     * @param $securePassword - секретный код
     * @param $decodeXml      - конвертировать ли xml в массив
     *
     * @throws Exception
     */
    public function __construct($account, $securePassword, $decodeXml = true)
    {
        if (
            is_string($account) && !empty($account)
            && is_string($securePassword) && !empty($securePassword)
        ) {
            $this->account        = trim($account);
            $this->securePassword = trim($securePassword);
        } else {
            throw new Exception('Account and securePassword might be a non empty string!');
        }

        $this->decodeXml = (bool)$decodeXml;
    }

    /**
     * Вычисляем значение поля secure
     *
     * @param $date - дата документа. Во всех модулях дата_время передается в формате UTC( 0000-00-00T00:00:00 ).
     */
    private function getSecure($date)
    {
        return md5($date . '&' . $this->securePassword);
    }

    /**
     * Предоставляет список ПВЗ, действующих на момент запроса
     *
     * @param string $url          - урл, на который будем стучать
     * @param null   $cityId       - код города по базе СДЭК
     * @param null   $cityPostCode - почтовый индекс города, для которого необходим список ПВЗ
     *                             Оба параметра необязательны, если указаны оба приоритет отдается cityid.
     *                             При отсутствии обоих параметров список ПВЗ содержит данные по всем городам
     *
     * @return object
     * @throws Exception
     */
    public function pvzList($cityId = null, $cityPostCode = null, $url = self::URL_PVZ_LIST_RESERVE)
    {
        $query = [];
        if (!empty($cityId))
            $query['cityid'] = $cityId;
        if (!empty($cityPostCode))
            $query['citypostcode'] = $cityPostCode;

        return $this->call('GET', $url, $this->decodeXml, null, $query);
    }

    /**
     * Отчет «Статусы заказов»
     * Запрос должен содержать хотя бы один из тэгов  ChangePeriod или Order.
     *
     * @param string $url         - урл, на который будем стучать
     * @param int    $showHistory - Атрибут, указывающий на необходимость загружать историю заказов (1-да, 0-нет)
     * @param        $dateFirst   - Дата начала запрашиваемого периода
     * @param        $dateLast    - Дата окончания запрашиваемого периода
     * @param        $orders      - массив заказов. каждый подмассив - определяет один заказ
     *                            пример массива:
     *                            $orders = [
     *                            [
     *                            'DispatchNumber' => $dispatch_number,        - Номер отправления СДЭК(присваивается при импорте заказов)
     *                            ],
     *                            [
     *                            'Number' => $number,                         - Номер отправления клиента
     *                            'Date' => $date                             - Дата акта приема-передачи, в котором был передан заказ
     *                            ]
     *                            ];
     *                            Идентификация заказа осуществляется либо по DispatchNumber, либо по двум параметрам Number, Date.
     *                            Если в запросе есть значение атрибута  DispatchNumber, то атрибуты  Number, Date игнорируются.
     *
     * @return object
     * @throws Exception
     */
    public function statusReport($showHistory = 1, $dateFirst = null, $dateLast = null, array $orders = null, $url = self::URL_STATUS_REPORT_RESERVE)
    {
        $dateNow = date('Y-m-d h:i:s');
        //собираем массив, из которого будет потом генерировать xml
        $data_for_xml = [
            '@attributes' => [
                'Date'        => $dateNow,
                'Account'     => $this->account,
                'Secure'      => $this->getSecure($dateNow),
                'ShowHistory' => (int)$showHistory,
            ],
        ];

        if (!empty($dateFirst)) {
            $data_for_xml['ChangePeriod'] = [
                '@attributes' => [
                    'DateFirst' => $dateFirst,
                    'DateLast'  => $dateLast,
                ],
            ];
        }

        if (!empty($orders)) {
            foreach ($orders as $order) {
                if ((!empty($order['DispatchNumber'])) || (!empty($order['Number']) && !empty($order['Date']))) {
                    $data_for_xml['Order'][] = [
                        '@attributes' => [
                            'DispatchNumber' => !empty($order['DispatchNumber']) ? $order['DispatchNumber'] : null,
                            'Number'         => !empty($order['Number']) ? $order['Number'] : null,
                            'Date'           => !empty($order['Date']) ? $order['Date'] : null,
                        ]
                    ];
                }
            }
        }

        if (!isset($data_for_xml['ChangePeriod']) && !isset($data_for_xml['Order'])) {
            throw new Exception('Either ChangePeriod (DateFirst) or Order (DispatchNumber or Number,Date) parameters must be specified!');
        }

        //генерируем xml из массива
        $xml = Array2XML::createXML('StatusReport', $data_for_xml)->saveXML();
        if (!$xml) {
            throw new Exception('Unable to generate XML body!');
        }
        $body = ['xml_request' => $xml];

        return $this->call('POST', $url, $this->decodeXml, $body);
    }

    /**
     * Вызов курьера
     *
     * @param string $url   - урл, на который будем стучать
     * @param array  $calls - массив  с подмассивами ожиданий курьера.
     *                      пример массива:
     *                      $calls = [
     *                      [
     *                      'Date'=> $date,     - Дата ожидания курьера
     *                      'TimeBeg'=> $timeBeg,   - Время начала ожидания курьера
     *                      'TimeEnd'=> $timeEnd,   -  Время окончания ожидания курьера
     *                      'LunchBeg'=> $lunchBeg,     - Время начала обеда, если входит во временной диапазон [TimeBeg; TimeEnd]
     *                      'LunchEnd'=> $lunchEnd,      - Время окончания обеда, если входит во временной диапазон [TimeBeg; TimeEnd]
     *                      'SendCityCode'=> $sendCityCode,      - Код города отправителя из базы СДЭК
     *                      'SendPhone'=> $sendPhone,        - Контактный телефон отправителя
     *                      'SenderName'=> $senderName,         - Отправитель (ФИО)
     *                      'Weight'=> $weight,         - Общий вес, в граммах
     *                      'Comment'=> $comment,        - Комментарий
     *                      'Street'=> $street,      - Улица
     *                      'House'=> $house,       - Дом, корпус, строение
     *                      'Flat'=> $flat,         - Квартира/Офис
     *                      ]
     *                      ];
     *
     * @return object
     * @throws Exception
     */
    public function callCourier($url = self::URL_CALL_COURIER_MAIN, array $calls)
    {
        $dateNow = date('Y-m-d h:i:s');
        //собираем массив, из которого будет потом генерировать xml
        $data_for_xml = [
            '@attributes' => [
                'Date'      => $dateNow,
                'Account'   => $this->account,
                'Secure'    => $this->getSecure($dateNow),
                'CallCount' => count($calls)
            ],
            'Call'        => [],
        ];
        foreach ($calls as $call) {
            $data_for_xml['Call'][] = [
                '@attributes' => [
                    'Date'         => isset($call['Date']) ? $call['Date'] : null,
                    'TimeBeg'      => isset($call['TimeBeg']) ? $call['TimeBeg'] : null,
                    'TimeEnd'      => isset($call['TimeEnd']) ? $call['TimeEnd'] : null,
                    'LunchBeg'     => isset($call['LunchBeg']) ? $call['LunchBeg'] : null,
                    'LunchEnd'     => isset($call['LunchEnd']) ? $call['LunchEnd'] : null,
                    'SendCityCode' => isset($call['SendCityCode']) ? $call['SendCityCode'] : null,
                    'SendPhone'    => isset($call['SendPhone']) ? $call['SendPhone'] : null,
                    'SenderName'   => isset($call['SenderName']) ? $call['SenderName'] : null,
                    'Weight'       => isset($call['Weight']) ? $call['Weight'] : null,
                    'Comment'      => isset($call['Comment']) ? $call['Comment'] : null,
                ],
                'Address'     => [
                    '@attributes' => [
                        'Street' => isset($call['Street']) ? $call['Street'] : null,
                        'House'  => isset($call['House']) ? $call['House'] : null,
                        'Flat'   => isset($call['Flat']) ? $call['Flat'] : null,
                    ],
                ]
            ];
        }

        //генерируем xml из массива
        $xml = Array2XML::createXML('CallCourier', $data_for_xml)->saveXML();
        if (!$xml) {
            throw new Exception('Unable to generate XML body!');
        }
        $body = ['xml_request' => $xml];

        return $this->call('POST', $url, $this->decodeXml, $body);
    }

    /**
     * Список заказов на доставку
     *
     * @param string $url         - урл, на который будем стучать
     * @param        $number      - Номер акта приема-передачи/ТТН (сопроводительного документа при передаче груза СДЭК, формируется в системе ИМ), так же используется для удаления заказов
     * @param array  $orders      -  массив заказов. каждый подмассив - определяет один заказ
     * @param array  $callCourier - Вызов курьера
     *
     * @return object
     * @throws Exception
     */
    public function deliveryRequest($number, $dateNow, array $orders, array $callCourier = null, $url = self::URL_DELIVERY_REQUEST_RESERVE)
    {
        if (empty($number) || empty($orders)) {
            throw new Exception('Variables $number and $orders cannot be empty!');
        }

        //$dateNow = date('Y-m-d h:i:s');
        //собираем массив, из которого будет потом генерировать xml
        $data_for_xml = [
            '@attributes' => [
                'Date'       => $dateNow,
                'Number'     => $number,
                'Account'    => $this->account,
                'Secure'     => $this->getSecure($dateNow),
                'OrderCount' => count($orders)
            ],
        ];
        foreach ($orders as $k => $order) {
            $data_for_xml['Order'][$k] = [
                '@attributes' => [
                    'Number'                => isset($order['Number']) ? $order['Number'] : null,
                    'SendCityCode'          => isset($order['SendCityCode']) ? $order['SendCityCode'] : null,
                    'RecCityCode'           => isset($order['RecCityCode']) ? $order['RecCityCode'] : null,
                    'SendCityPostCode'      => isset($order['SendCityPostCode']) ? $order['SendCityPostCode'] : null,
                    'RecCityPostCode'       => isset($order['RecCityPostCode']) ? $order['RecCityPostCode'] : null,
                    'RecipientName'         => isset($order['RecipientName']) ? $order['RecipientName'] : null,
                    'RecipientEmail'        => isset($order['RecipientEmail']) ? $order['RecipientEmail'] : null,
                    'Phone'                 => isset($order['Phone']) ? $order['Phone'] : null,
                    'TariffTypeCode'        => isset($order['TariffTypeCode']) ? $order['TariffTypeCode'] : null,
                    'DeliveryRecipientCost' => isset($order['DeliveryRecipientCost']) ? $order['DeliveryRecipientCost'] : null,
                    'RecipientCurrency'     => isset($order['RecipientCurrency']) ? $order['RecipientCurrency'] : null,
                    'ItemsCurrency'         => isset($order['ItemsCurrency']) ? $order['ItemsCurrency'] : null,
                    'Comment'               => isset($order['Comment']) ? $order['Comment'] : null,
                    'SellerName'            => isset($order['SellerName']) ? $order['SellerName'] : null,
                ],
                'Address'     => [
                    '@attributes' => [
                        'Street'  => isset($order['Address']['Street']) ? $order['Address']['Street'] : null,
                        'House'   => isset($order['Address']['House']) ? $order['Address']['House'] : null,
                        'Flat'    => isset($order['Address']['Flat']) ? $order['Address']['Flat'] : null,
                        'PvzCode' => isset($order['Address']['PvzCode']) ? $order['Address']['PvzCode'] : null,
                    ],
                ],
            ];
            if (!empty($order['Packages'])) {
                foreach ($order['Packages'] as $k_p => $package) {
                    $data_for_xml['Order'][$k]['Package'][$k_p] = [
                        '@attributes' => [
                            'Number'  => isset($package['Number']) ? $package['Number'] : null,
                            'BarCode' => isset($package['BarCode']) ? $package['BarCode'] : null,
                            'Weight'  => isset($package['Weight']) ? $package['Weight'] : null,
                            'SizeA'   => isset($package['SizeA']) ? $package['SizeA'] : null,
                            'SizeB'   => isset($package['SizeB']) ? $package['SizeB'] : null,
                            'SizeC'   => isset($package['SizeC']) ? $package['SizeC'] : null,
                        ]
                    ];
                    /*if (isset($package['SizeA'])) {
                        $data_for_xml['Order'][$k]['Package'][$k_p]['@attributes'] = $package['SizeA'];
                    }
                    if (isset($package['SizeB'])) {
                        $data_for_xml['Order'][$k]['Package'][$k_p]['@attributes'] = $package['SizeB'];
                    }
                    if (isset($package['SizeC'])) {
                        $data_for_xml['Order'][$k]['Package'][$k_p]['@attributes'] = $package['SizeC'];
                    }*/

                    if (!empty($package['Items'])) {
                        foreach ($package['Items'] as $item) {
                            $data_for_xml['Order'][$k]['Package'][$k_p]['Item'][] = [
                                '@attributes' => [
                                    'WareKey' => isset($item['WareKey']) ? $item['WareKey'] : null,
                                    'Cost'    => isset($item['Cost']) ? $item['Cost'] : null,
                                    'Payment' => isset($item['Payment']) ? $item['Payment'] : null,
                                    'Weight'  => isset($item['Weight']) ? $item['Weight'] : null,
                                    'Amount'  => isset($item['Amount']) ? $item['Amount'] : null,
                                    'Comment' => isset($item['Comment']) ? $item['Comment'] : null,
                                ]
                            ];
                        }
                    }
                }
            }

            if (!empty($order['AddServices'])) {
                foreach ($order['AddServices'] as $addService) {
                    $data_for_xml['Order'][$k]['AddService'][] = [
                        '@attributes' => [
                            'ServiceCode' => isset($addService['ServiceCode']) ? $addService['ServiceCode'] : null
                        ],
                        '@value' => ''
                    ];
                }
            }
            if (!empty($order['Schedule']) && !empty($order['Schedule']['Attempts'])) {
                foreach ($order['Schedule']['Attempts'] as $k_a => $attempt) {
                    $data_for_xml['Order'][$k]['Schedule']['Attempt'][$k_a] = [
                        '@attributes' => [
                            'ID'            => isset($attempt['ID']) ? $attempt['ID'] : null,
                            'Date'          => isset($attempt['Date']) ? $attempt['Date'] : null,
                            'TimeBeg'       => isset($attempt['TimeBeg']) ? $attempt['TimeBeg'] : null,
                            'TimeEnd'       => isset($attempt['TimeEnd']) ? $attempt['TimeEnd'] : null,
                            'RecipientName' => isset($attempt['RecipientName']) ? $attempt['RecipientName'] : null,
                            'Phone'         => isset($attempt['Phone']) ? $attempt['Phone'] : null,
                            'Comment'       => isset($attempt['Comment']) ? $attempt['Comment'] : null,
                        ]
                    ];
                    if (isset($attempt['Address'])) {
                        $data_for_xml['Order'][$k]['Schedule']['Attempt'][$k_a]['Address'] = [
                            '@attributes' => [
                                'Street'  => isset($attempt['Address']['Street']) ? $attempt['Address']['Street'] : null,
                                'House'   => isset($attempt['Address']['House']) ? $attempt['Address']['House'] : null,
                                'Flat'    => isset($attempt['Address']['Flat']) ? $attempt['Address']['Flat'] : null,
                                'PvzCode' => isset($attempt['Address']['PvzCode']) ? $attempt['Address']['PvzCode'] : null,
                            ]
                        ];
                    }
                }
            }
        }
        if (!empty($callCourier)) {
            $data_for_xml['CallCourier'] = [
                'Call' => [
                    '@attributes' => [
                        'Date'         => isset($callCourier['Date']) ? $callCourier['Date'] : null,
                        'TimeBeg'      => isset($callCourier['TimeBeg']) ? $callCourier['TimeBeg'] : null,
                        'TimeEnd'      => isset($callCourier['TimeEnd']) ? $callCourier['TimeEnd'] : null,
                        'LunchBeg'     => isset($callCourier['LunchBeg']) ? $callCourier['LunchBeg'] : null,
                        'LunchEnd'     => isset($callCourier['LunchEnd']) ? $callCourier['LunchEnd'] : null,
                        'SendCityCode' => isset($callCourier['SendCityCode']) ? $callCourier['SendCityCode'] : null,
                        'SendPhone'    => isset($callCourier['SendPhone']) ? $callCourier['SendPhone'] : null,
                        'SenderName'   => isset($callCourier['SenderName']) ? $callCourier['SenderName'] : null,
                        'Comment'      => isset($callCourier['Comment']) ? $callCourier['Comment'] : null,
                    ],
                    'SendAddress' => [
                        '@attributes' => [
                            'Street' => isset($callCourier['Street']) ? $callCourier['Street'] : null,
                            'House'  => isset($callCourier['House']) ? $callCourier['House'] : null,
                            'Flat'   => isset($callCourier['Flat']) ? $callCourier['Flat'] : null,
                        ],
                    ]
                ],
            ];
        }

        //генерируем xml из массива
        $xml = Array2XML::createXML('DeliveryRequest', $data_for_xml)->saveXML();
        if (!$xml) {
            throw new Exception('Unable to generate XML body!');
        }
        $body = ['xml_request' => $xml];

        return $this->call('POST', $url, $this->decodeXml, $body);
    }

    public function infoRequest(array $orders, $url = self::URL_INFO_REQUEST_RESERVE)
    {
        if (empty($orders)) {
            throw new Exception('Variables $number and $orders cannot be empty!');
        }

        $dateNow = date('Y-m-d h:i:s');

        $data_for_xml = [
            '@attributes' => [
                'Date'       => $dateNow,
                'Account'    => $this->account,
                'Secure'     => $this->getSecure($dateNow),
            ],
        ];
        foreach ($orders as $k => $order) {
            $data_for_xml['Order'][$k] = [
                '@attributes' => [
                    'DispatchNumber'  => isset($order['DispatchNumber']) ? $order['DispatchNumber'] : null,
                    'Number'          => isset($order['Number']) ? $order['Number'] : null,
                    'Date'            => isset($order['Date']) ? $order['Date'] : null,
                ],
            ];
        }

        $xml = Array2XML::createXML('InfoRequest', $data_for_xml)->saveXML();
        if (!$xml) {
            throw new Exception('Unable to generate XML body!');
        }
        $body = ['xml_request' => $xml];

        return $this->call('POST', $url, $this->decodeXml, $body);
    }

    public function deleteRequest($number, array $orders, $url = self::URL_DELETE_ORDERS_MAIN)
    {
        if (empty($orders) || empty($number)) {
            throw new Exception('Variables $number and $orders cannot be empty!');
        }

        $dateNow = date('Y-m-d h:i:s');

        $data_for_xml = [
            '@attributes' => [
                'Number'     => $number, 
                'Date'       => $dateNow,
                'Account'    => $this->account,
                'Secure'     => $this->getSecure($dateNow),
                'OrderCount' => count($orders),
            ],
        ];
        foreach ($orders as $k => $order) {
            $data_for_xml['Order'][$k] = [
                '@attributes' => [
                    'Number' => isset($order['Number']) ? $order['Number'] : null,
                ],
            ];
        }

        $xml = Array2XML::createXML('DeleteRequest', $data_for_xml)->saveXML();
        if (!$xml) {
            throw new Exception('Unable to generate XML body!');
        }
        $body = ['xml_request' => $xml];

        return $this->call('POST', $url, $this->decodeXml, $body);
    }

    /** Общий метод для отправки запроса
     *
     * @param       $method    - метод (GET, POST, ....)
     * @param       $url       - на какой url отправлять запрос
     * @param       $decodeXml - конвертировать ли xml в массив
     * @param mixed $body      - тело запроса (для метода POST)
     * @param array $query     - параметры GET запроса
     *
     * @return object
     * @throws Exception
     */
    private function call($method, $url, $decodeXml = true, $body = null, array $query = [])
    {
        try {
            $client  = new Client();
            $request = $client->createRequest($method, $url, null, $body, ['query' => $query]);

            $response = $request->send();
            if ($response->isSuccessful()) {
                if (!$response->getBody()->getContentLength()) {
                    return (object)['result' => null, 'url' => $request->getUrl()];
                }

                //т.к. апи даже в случае ошики отдает 200, то сами проверяем на наличие ошибки
//                $result_xml_attrs = (array)$response->xml()->attributes();
//                $result_xml_attrs = $result_xml_attrs['@attributes'];
//                if(isset($result_xml_attrs['ErrorCode'])){
//                    throw new Exception($result_xml_attrs['ErrorCode'].': '.$result_xml_attrs['Msg']);
//                }

                if ($decodeXml) {
                    $result = (object)['result' => XML2Array::createArray($response->getBody(true)), 'url' => $request->getUrl()];
                } else {
                    $result = (object)['result' => $response->getBody(true), 'url' => $request->getUrl()];
                }

                return $result;
            }

            throw new Exception($response->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    static public function getStatusesList()
    {
        return [
            self::STATUS_CREATED                          => [
                'name'    => 'Создан',
                'comment' => 'Заказ зарегистрирован в базе данных СДЭК'
            ],
            self::STATUS_DELETED                          => [
                'name'    => 'Удален',
                'comment' => 'Заказ отменен ИМ после регистрации в системе до прихода груза на склад СДЭК в городе-отправителе'
            ],
            self::STATUS_TAKEN_TO_SENDER_STORAGE          => [
                'name'    => 'Принят на склад отправителя',
                'comment' => 'Оформлен приход на склад СДЭК в городе-отправителе'
            ],
            self::STATUS_SENT_FROM_SENDER_CITY            => [
                'name'    => 'Выдан на отправку в г.-отправителе',
                'comment' => 'Оформлен расход со склада СДЭК в городе-отправителе. Груз подготовлен к отправке (консолидирован с другими посылками)'
            ],
            self::STATUS_RETURNED_TO_SENDER_STORAGE       => [
                'name'    => 'Возвращен на склад отправителя',
                'comment' => 'Повторно оформлен приход в городе-отправителе (не удалось передать перевозчику по какой-либо причине)'
            ],
            self::STATUS_GIVEN_TO_CARRIER_IN_SENDER_CITY  => [
                'name'    => 'Сдан перевозчику в г.-отправителе',
                'comment' => 'Зарегистрирована отправка в городе-отправителе. Консолидированный груз передан на доставку (в аэропорт/загружен машину)'
            ],
            self::STATUS_SENT_TO_TRANSIT_CITY             => [
                'name'    => 'Отправлен в г.-транзит',
                'comment' => 'Зарегистрирована отправка в город-транзит. Проставлены дата и время отправления у перевозчика'
            ],
            self::STATUS_MET_IN_TRANSIT_CITY              => [
                'name'    => 'Встречен в г.-транзите',
                'comment' => 'Зарегистрирована встреча в городе-транзите'
            ],
            self::STATUS_TAKEN_TO_TRANSIT_STORAGE         => [
                'name'    => 'Принят на склад транзита',
                'comment' => 'Оформлен приход в городе-транзите'
            ],
            self::STATUS_RETURNED_TO_TRANSIT_STORAGE      => [
                'name'    => 'Возвращен на склад транзита',
                'comment' => 'Повторно оформлен приход в городе-транзите (груз возвращен на склад)'
            ],
            self::STATUS_SENT_FROM_TRANSIT_CITY           => [
                'name'    => 'Выдан на отправку в г.-транзите',
                'comment' => 'Оформлен расход в городе-транзите'
            ],
            self::STATUS_GIVEN_TO_CARRIER_IN_TRANSIT_CITY => [
                'name'    => 'Сдан перевозчику в г.-транзите',
                'comment' => 'Зарегистрирована отправка у перевозчика в городе-транзите'
            ],
            self::STATUS_SENT_TO_RECIPIENT_CITY           => [
                'name'    => 'Отправлен в г.-получатель',
                'comment' => 'Зарегистрирована отправка в город-получатель, груз в пути'
            ],
            self::STATUS_MET_IN_RECIPIENT_CITY            => [
                'name'    => 'Встречен в г.-получателе',
                'comment' => 'Зарегистрирована встреча груза в городе-получателе'
            ],
            self::STATUS_TAKEN_TO_DELIVERY_STORAGE        => [
                'name'    => 'Принят на склад доставки',
                'comment' => 'Оформлен приход на склад города-получателя, ожидает доставки до двери'
            ],
            self::STATUS_TAKEN_TO_STORAGE_TILL_DEMAND     => [
                'name'    => 'Принят на склад до востребования',
                'comment' => 'Оформлен приход на склад города-получателя. Доставка до склада, посылка ожидает забора клиентом - покупателем ИМ'
            ],
            self::STATUS_GIVEN_FOR_DELIVERY               => [
                'name'    => 'Выдан на доставку',
                'comment' => 'Добавлен в курьерскую карту, выдан курьеру на доставку'
            ],
            self::STATUS_RETURNED_TO_DELIVERY_STORAGE     => [
                'name'    => 'Возвращен на склад доставки',
                'comment' => 'Оформлен повторный приход на склад в городе-получателе. Доставка не удалась по какой-либо причине, ожидается очередная попытка доставки'
            ],
            self::STATUS_HANDED                           => [
                'name'    => 'Вручен',
                'comment' => 'Успешно доставлен и вручен адресату'
            ],
            self::STATUS_NOT_HANDED                       => [
                'name'    => 'Не вручен',
                'comment' => 'Покупатель отказался от покупки, возврат в ИМ'
            ]
        ];
    }
}
