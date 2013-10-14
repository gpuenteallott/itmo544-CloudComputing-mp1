<?php
################################################
# Illinois Institute of Technology
# ITMO 544 Cloud Computing - Mini Project 1 
#
# Student: Guillermo de la Puente
#          https://github.com/gpuenteallott
#
# Script C -
# will deploy the load balancer and register your two instances with the load
# balancer. Also create a SimpleDB domain, an SQS queue, and SNS Topic – it can be the
# same script or another PHP script run from the command line 1 time
#
# Usage:
#        ./install.php name custom_config_file_path
#        name is the identificator of SDB domain,QS queue and SNS topic
#        custom_config_file_path is the path to the php file that returns information with the aws credentials
#
# Example: ./install.php itmo544 "/var/www/itmo544-CloudComputing-mp1/custom-config.php"
################################################

// Include the SDK using the Composer autoloader
require 'vendor/autoload.php';

# Uncomment this for better debugging
#header("Content-type: text/plain; charset=utf-8");

use Aws\Common\Aws;
use Aws\SimpleDb\SimpleDbClient;
use Aws\S3\S3Client;
use Aws\Sns\SnsClient;
use Aws\Sqs\sqsclient;
use Aws\Sns\Exception\InvalidParameterException;
print "started";
$aws = Aws::factory('./vendor/aws/aws-sdk-php/src/Aws/Common/Resources/custom-config.php');

$client = $aws->get('S3'); 

$sdbclient = $aws->get('SimpleDb'); 

$snsclient = $aws->get('Sns'); 

$sqsclient = $aws->get('Sqs');


# Read the name file
# Name is the resources identifier in AWS for this system
$NAME = file_get_contents("name.txt");
$NAME_SDB = str_replace("-", "", $NAME)."sdb";


$UUID = uniqid();
$email = str_replace("@","-",$_POST["email"]); 
$bucket = str_replace("@","-",$_POST["email"]).time();
$bucket = str_replace(" ","","$NAME-s3-$bucket"); 
print "bucket name: $bucket\n";
$phone = $_POST["phone"];
# Previous topic configuration
#$topic = explode("-",$email );
$topic = "$NAME-sns";
$itemName = 'images-'.$UUID;


#echo $topic[0]."\n";
#############################################
# Create SNS Simple Notification Service Topic for subscription
##############################################
/*
$result = $snsclient->createTopic(array(
    // Name is required
    'Name' => $topic,
));

$topicArn = $result['TopicArn'];

echo $topicArn ."\n";
echo $phone ."\n";

$result = $snsclient->setTopicAttributes(array(
    // TopicArn is required
    'TopicArn' => $topicArn,
    // AttributeName is required
    'AttributeName' => 'DisplayName',
    'AttributeValue' => "$NAME",
));

try {
$result = $snsclient->subscribe(array(

    'TopicArn' => $topicArn,

    'Protocol' => 'sms',
    'Endpoint' => $phone,
)); } catch(InvalidParameterException $i) {
 echo 'Invalid parameter: '. $i->getMessage() . "\n";
} 

# see send for actual sending of text message

*/


###############################################################
# S3
# add uploaded photo to a S3 bucket called after the user email
# set metadata tags for a md5 hash and an epoch timestamp
# return S3 URI for uploaded object
###############################################################

$result = $client->createBucket(array(
    'Bucket' => $bucket
));
print "bucket creating: $bucket\n";
// Wait until the bucket is created
$client->waitUntil('BucketExists', array('Bucket' => $bucket));
print "bucket created: $bucket\n";
$uploaddir = '/tmp/';
$uploadfile = $uploaddir . basename($_FILES['uploaded_file']['name']);
echo $uploadfile. "\n";
if (move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $uploadfile)) {
    echo "File is valid, and was successfully uploaded.\n";
} else {
    echo "Possible file upload attack!\n";
}
$pathToFile = $uploaddir.$_FILES['uploaded_file']['name'];
#echo 'Here is some more debugging info:';
#print_r($_FILES);

// Upload an object by streaming the contents of a file
// $pathToFile should be absolute path to a file on disk
$result = $client->putObject(array(
    'ACL'        => 'public-read',
    'Bucket'     => $bucket,
    'Key'        => $_FILES['uploaded_file']['name'],
    'SourceFile' => $pathToFile,
    'Metadata'   => array(
        'timestamp' => time(),
        'md5' =>  md5_file($pathToFile),
    )
));
print "#############################\n";
var_export($result->getkeys());
// this gets all the key value pairs and exports them as system variables making our lives nice so we don't have to do this manually. 

# S3 URI for uploaded object
$url= $result['ObjectURL'];




####################################################
# SimpleDB
# create Item in SimpleDB that contains:
# rawurl, email, bucketname, filename, phone, id, finishedurl
# The initial value of finishedurl is empty, will be filled when the processing is done
###################################################

# The domain was already created in setup
# Just in case, to avoid potential errors, create it
$result = $sdbclient->createDomain(array(
    'DomainName' => "$NAME_SDB", 
));

$result = $sdbclient->putAttributes(array(

    'DomainName' => "$NAME_SDB",
    'ItemName' =>$itemName ,
    'Attributes' => array(
        array(
           'Name' => 'rawurl',
            'Value' => $url,
        ),
        array(
           'Name' => 'bucket',
            'Value' => $bucket,
        ),
        array(
           'Name' => 'id',
           'Value' => $UUID,
            ),  
        array(
            'Name' =>  'email',
            'Value' => $_POST['email'],
         ),
        array(
            'Name' => 'phone',
            'Value' => $phone,
        ),
         array(
            'Name' => 'finishedurl',
            'Value' => '',
        ),  
        array(
            'Name' => 'receiptHandle',
            'Value' => '',
        ),      
         array(
            'Name' => 'filename',
            'Value' => basename($_FILES['uploaded_file']['name']),
        ), 
    ),
));


#####################################################
# SNS publishing of message to topic - which will be sent via SMS
#####################################################
/*
$result = $snsclient->publish(array(
    'TopicArn' => $topicArn,
    'TargetArn' => $topicArn,
    // Message is required
    'Message' => 'Your image has been uploaded',
    'Subject' => $url,
    'MessageStructure' => 'sms',
));
*/

#####################################################
# SQS
# place a queue with the id as the sqs body
#####################################################

# Obtain the SQS url for the given name
$sqs_queue_url = $sqsclient->getQueueUrl(array(
    'QueueName' => "$NAME-sqs",
));
var_export($sqs_queue_url->getkeys());
$sqs_queue_url = $sqs_queue_url['QueueUrl'];

# Send the message
$result = $sqsclient->sendMessage(array(

    'QueueUrl' => $sqs_queue_url,
    'MessageBody' => $UUID,
    'DelaySeconds' => 15,
));


?>
<!DOCTYPE html>
<html>
<head>
    <title>Process</title>
</head>

<body>
    <h1>Picture Uploader</h1>

    <p>A mini project for ITMO 544 - Cloud Computing</p>
    <p>Illinois Institute of Technology</p>
    <p>Student: Guillermo de la Puente</p>
    <p><a href="https://github.com/gpuenteallott/itmo544-CloudComputing-mp1">Project in GitHub</a></p>

    <h2>Fill the following form</h2>


    <p>Thank you</p>
    <p>S3 bucket: <? echo $bucket ?></p>

    <p>Continue to next step --> <a href="resize.php">Resize</a></p>
</body>
</html>
