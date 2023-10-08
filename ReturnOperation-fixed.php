<?php


/*
This code appears to be used for handling notification operations in a business context. Specifically, it is related to TsReturn operations, which could refer to the process of returning goods or handling customer complaints. The code is responsible for sending notifications to relevant parties, such as employees and clients, regarding changes or updates related to TsReturn operations. These notifications can be sent via email or SMS. The purpose of these notifications is to keep the involved parties informed and facilitate communication within the business workflow.
*/
namespace NW\WebService\References\Operations\Notification;


/*
This is a class called TsReturnOperation that extends the ReferencesOperation class. It contains a method called doOperation() that returns an array. 

The doOperation() method performs various operations based on the data received from the request. It first retrieves the 'resellerId' and 'notificationType' from the data and initializes a result array. 

If the 'resellerId' is empty, it sets a message in the result array and returns it. If the 'notificationType' is empty, it throws an exception.

Next, it retrieves the reseller and client information based on the resellerId and clientId. If the reseller or client is not found, it throws an exception.

The method then retrieves additional data from the request and checks if any variables for the template are empty. If any of the variables are empty, it throws an exception.

It then retrieves the email from the reseller and gets the emails of employees from the settings. If there are emails and the reseller email is not empty, it sends an email notification to the employees and sets a flag in the result array.

If the notification type is 'TYPE_CHANGE' and there is a difference in the status, it sends a client email notification and/or an SMS notification. It sets flags in the result array accordingly.

Finally, it returns the result array.

*/
class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW    = 1;
    public const TYPE_CHANGE = 2;

    /**
     * @throws \Exception
     */
    public function doOperation(): array  //  void => array
    {
        $data = (array)$this->getRequest('data');
        $resellerId = $data['resellerId'];
        $notificationType = (int)$data['notificationType'];
        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail'   => false,
            'notificationClientBySms'     => [
                'isSent'  => false,
                'message' => '',
            ],
        ];

        if (empty((int)$resellerId)) {
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result;
        }

        if (empty((int)$notificationType)) {
            throw new \Exception('Empty notificationType', 400);
        }

        $reseller = Seller::getById((int)$resellerId);
        if ($reseller === null) {
            throw new \Exception('Seller not found!', 400);
        }

        //
        $clientId = (int)$data['clientId'];
        //
        $client = Contractor::getById((int)$data['clientId']);
        if ($client === null || $client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $resellerId) {
            throw new \Exception('сlient not found!', 400);
        }


        // ????
        $cFullName = $client->getFullName();
        if (empty($cFullName)) {
            $cFullName = $client->name;
        }

        $creatorId = (int)$data['creatorId'];
        $cr = Employee::getById((int)$data['creatorId']);
        if ($cr === null) {
            throw new \Exception('Creator not found!', 400);
        }

        $expertId = (int)$data['expertId'];
        $et = Employee::getById((int)$data['expertId']);
        if ($et === null) {
            throw new \Exception('Expert not found!', 400);
        }

        $differences = '';
        
        if ($notificationType === self::TYPE_NEW) {
            $differences = 'NewPositionAdded';
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            $differences = 'PositionStatusHasChanged';
        }

        

        $templateData = [
            'COMPLAINT_ID'       => (int)$data['complaintId'],
            'COMPLAINT_NUMBER'   => (string)$data['complaintNumber'],
            'CREATOR_ID'         => (int)$data['creatorId'],
            'CREATOR_NAME'       => $cr->getFullName(),
            'EXPERT_ID'          => (int)$data['expertId'],
            'EXPERT_NAME'        => $et->getFullName(),
            'CLIENT_ID'          => (int)$data['clientId'],
            'CLIENT_NAME'        => $cFullName,
            'CONSUMPTION_ID'     => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER'   => (string)$data['agreementNumber'],
            'DATE'               => (string)$data['date'],
            'DIFFERENCES'        => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        $emailFrom = getResellerEmailFrom($resellerId);
        // Получаем email сотрудников из настроек
        $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {

                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                           'emailFrom' => $emailFrom,
                           'emailTo'   => $email,
                           'subject'   => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                           'message'   => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
                $result['notificationEmployeeByEmail'] = true;

            }
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            if (!empty($emailFrom) && !empty($client->email)) {
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                           'emailFrom' => $emailFrom,
                           'emailTo'   => $client->email,
                           'subject'   => __('complaintClientEmailSubject', $templateData, $resellerId),
                           'message'   => __('complaintClientEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to']);
                $result['notificationClientByEmail'] = true;
            }

            if (!empty($client->mobile)) {
                $res = NotificationManager::send($resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to'], $templateData, $error);
                if ($res) {
                    $result['notificationClientBySms']['isSent'] = true;
                }
                if (!empty($error)) {
                    $result['notificationClientBySms']['message'] = $error;
                }
            }

            
        }

        return $result;
    }
}
