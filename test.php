<?php
require('CdekSdk.php');
require('vendor/autoload.php');
try {
    $calls = [
        [
            'TimeBeg' => 'sdf',
            'TimeEnd' => 'sdf',
            'LunchBeg' => 'sdf',
            'LunchEnd' => 'sdf',
            'SendCityCode' => 'sdf',
            'SendPhone' => 'sdf',
            'SenderName' => 'sdf',
            'Weight' => 'sdf',
            'Comment' => 'sdf',
            'Street' => 'sdf',
            'House' => 'sdf',
            'Flat' => 'sdf',
        ],
        [
            'Date' => 'qq',
            'TimeBeg' => 'qq',
            'TimeEnd' => 'qq',
            'LunchBeg' => 'qq',
            'LunchEnd' => 'qq',
            'SendCityCode' => 'qq',
            'SendPhone' => 'qq',
            'SenderName' => 'qq',
            'Weight' => 'qq',
            'Comment' => 'qq',
            'Street' => 'qq',
            'House' => 'qq',
            'Flat' => 'qq',
        ],
    ];

//    $orders = [
//        //------1
//        [
//            'Number' => 5408,
//            'DeliveryRecipientCost' => 0,
//            'SendCityCode' => 270,
//            'RecCityCode' => 44,
//            'Phone' => "7810999, 9295849151",
//            'RecipientName' => "Васина Юлия Александровна",
//            'Comment' => "",
//            'TariffTypeCode' => 5,
//            'Address' => [
//                'PvzCode' => "MSK2",
//            ],
//            'Packages' => [
//                [
//                    'Number' => 1,
//                    'BarCode' => 101,
//                    'Weight' => 630,
//                    'Items' => [
//                        [
//                            'WareKey' => "25000050368",
//                            'Cost' => 49,
//                            'Payment' => 49,
//                            'Weight' => 68,
//                            'Amount' => 1,
//                            'Comment' => "Comment",
//                        ],
//                        [
//                            'WareKey' => "25000348563",
//                            'Cost' => 79,
//                            'Payment' => 79,
//                            'Weight' => 95,
//                            'Amount' => 1,
//                            'Comment' => "Comment",
//                        ],
//                        [
//                            'WareKey' => "25000373314",
//                            'Cost' => 79,
//                            'Payment' => 79,
//                            'Weight' => 135,
//                            'Amount' => 1,
//                            'Comment' => "Comment",
//                        ],
//
//                    ],
//                ],
//            ],
//            'AddServices' => [
//                [
//                    'ServiceCode' => 30,
//                ],
//                [
//                    'ServiceCode' => 3,
//                ]
//            ],
//            'Schedule' => [
//                'Attempts' => [
//                    [
//                        'ID' => 1,
//                        'Date' => "2015-06-15",
//                        'TimeEnd' => "13:00:00",
//                        'TimeBeg' => "09:00:00",
//                    ],
//                    [
//                        'ID' => 2,
//                        'Date' => "201-06-16",
//                        'TimeEnd' => "18:00:00",
//                        'TimeBeg' => "14:00:00",
//                        'RecipientName' => "Прокопьев Анатолий Сергеевич",
//                    ],
//                ],
//            ],
//        ],
//        //-----2
//        [
//            'Number' => 5407,
//            'DeliveryRecipientCost' => 150,
//            'SendCityCode' => 270,
//            'RecCityCode' => 44,
//            'Phone' => "9197747341",
//            'RecipientName' => "Залещанский Андрей Борисович",
//            'Comment' => "Comment2",
//            'TariffTypeCode' => 11,
//            'Address' => [
//                'Street' => "Боровая",
//                'House' => "д. 7, стр. 2",
//                'Flat' => "оф.10",
//            ],
//            'Packages' => [
//                [
//                    'Number' => 1,
//                    'BarCode' => 102,
//                    'Weight' => 810,
//                    'Items' => [
//                        [
//                            'WareKey' => "25000358171",
//                            'Cost' => 164,
//                            'Payment' => 0,
//                            'Weight' => 158,
//                            'Amount' => 1,
//                            'Comment' => "Comment3",
//                        ],
//                        [
//                            'WareKey' => "25000428787",
//                            'Cost' => 107,
//                            'Payment' => 79,
//                            'Weight' => 0,
//                            'Amount' => 2,
//                            'Comment' => "Comment4",
//                        ],
//                        [
//                            'WareKey' => "33000002164",
//                            'Cost' => 79,
//                            'Payment' => 79,
//                            'Weight' => 147,
//                            'Amount' => 1,
//                            'Comment' => "Comment5",
//                        ],
//
//                    ],
//                ],
//                [
//                    'Number' => 2,
//                    'BarCode' => 103,
//                    'Weight' => 740,
//                    'Items' => [
//                        [
//                            'WareKey' => "25000086458",
//                            'Cost' => 107,
//                            'Payment' => 79,
//                            'Weight' => 0,
//                            'Amount' => 2,
//                            'Comment' => "Comment4",
//                        ],
//                        [
//                            'WareKey' => "25000377899",
//                            'Cost' => 79,
//                            'Payment' => 79,
//                            'Weight' => 147,
//                            'Amount' => 1,
//                            'Comment' => "Comment5",
//                        ],
//
//                    ],
//                ],
//            ],
//            'AddServices' => [
//                [
//                    'ServiceCode' => 3,
//                ],
//                [
//                    'ServiceCode' => 30,
//                ]
//            ],
//            'Schedule' => [
//                'Attempts' => [
//                    [
//                        'ID' => 3,
//                        'Date' => "2010-10-15",
//                        'TimeEnd' => "18:00:00",
//                        'TimeBeg' => "14:00:00",
//                        'RecipientName' => "Прокопьев Анатолий Сергеевич",
//                    ],
//                ],
//            ],
//        ],
//    ];
    $inst = new box2box\cdek\CdekSdk('bfec96cacce8e94b07833a7d2136917e', 'bed9a8c2de4aff3a5942259bb8a76e7e', false);
//     $res = $inst->statusReport(box2box\cdek\CdekSdk::URL_STATUS_REPORT_MAIN, 1, null,null,[['DispatchNumber'=>1014918451]]);
//     $res = $inst->deliveryRequest(box2box\cdek\CdekSdk::URL_DELIVERY_REQUEST_MAIN, 'sdfsdffsdf285s18fsdf', $orders);
    $res = $inst->callCourier(box2box\cdek\CdekSdk::URL_CALL_COURIER_MAIN, $calls);


    print_r($res);

} catch (Exception $e) {
    print_r($e);
    exit;
}
