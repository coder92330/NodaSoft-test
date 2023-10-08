<?php

namespace NW\WebService\References\Operations\Notification;

/**
 * @property Seller $Seller
 * 
 * The purpose of this code in a business context is to handle notification operations related to TsReturn processes. It facilitates communication and keeps relevant parties informed about changes or updates in TsReturn operations. This code enables the business to send notifications to employees and clients via email and SMS, ensuring that they receive timely information about the status of TsReturn operations. By providing notifications, the code helps streamline communication, improve customer satisfaction, and maintain efficient workflows within the business.
 */


 /*
 The given code consists of several classes and functions related to contractors, sellers, employees, statuses, references operations, and notification events.

The "Contractor" class is a base class that represents a contractor. It has properties such as id, type, and name. It also has a static method "getById" which takes a resellerId as input, validates it, and returns a new instance of the Contractor class.

The "Seller" and "Employee" classes are subclasses of the Contractor class. They inherit the properties and methods of the Contractor class.

The "Status" class represents the status of a contractor. It has properties like id and name. It also has a static method "getName" which takes a status id as input and returns the corresponding name of the status.

The "ReferencesOperation" class is an abstract class that defines an abstract method "doOperation". It also has a non-abstract method "getRequest" which takes a parameter name and a request array, and returns the value of the parameter from the request array.

The "getResellerEmailFrom" function returns the email address of the contractor.

The "getEmailsByPermit" function takes a resellerId and an event as input, checks if the resellerId is authorized, and returns an array of email addresses.

The "NotificationEvents" class defines constants for different notification events related to return status changes.

Overall, the code represents a basic structure for managing contractors, their statuses, and performing various operations related to them.
 */
class Contractor
{
    const TYPE_CUSTOMER = 0;
    public $id;
    public $type;
    public $name;

    public static function getById(int $resellerId): self
    {
        $resellerId = filter_var($resellerId, FILTER_VALIDATE_INT); 
        if (!$resellerId) { 
            throw new Exception('Invalid input'); 
        } 
        return new self($resellerId); 
    }

    public function getFullName(): string
    {
        return $this->name . ' ' . $this->id;
    }
}

class Seller extends Contractor
{
}

class Employee extends Contractor
{
}

class Status
{
    public $id, $name;

    public static function getName(int $id): string
    {

        switch ($id) {
          case 0:
            return 'completed';
            break;
          case 1:
            return 'pending';
            break;
          case 2:
            return'rejected';
            break;
          default:
            throw new Exception('Invalid status ID'); 
            break;
        }

    }
}

abstract class ReferencesOperation
{
    abstract public function doOperation(): array;


    public function getRequest(string $pName, array $request): ?string
    {
        return $request[$pName] ?? null;
        
    }
}


function getResellerEmailFrom(): string
{
    return 'contractor@example.com';
}


function getEmailsByPermit(int $resellerId, string $event): array
{
    if ($resellerId !== $_SESSION['reseller_id']) {
        throw new Exception('Unauthorized access');
    }
    // fakes the method
    return ['someemail@example.com', 'someemail2@example.com'];
}

class NotificationEvents
{
    const CHANGE_RETURN_STATUS = 'changeReturnStatus';
    const NEW_RETURN_STATUS    = 'newReturnStatus';
}
