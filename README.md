# A PHP library to fetch IMAP messages

## Features

- Fetch, delete and undelete messages from an IMAP mailbox.
- Decode encodings (for instance from base64).
- Decode all character sets to UTF-8 (for instance from Latin1).
- Works with PHP 5.3+

## Install

### With composer

`composer require mlocati/imap`

### Without composer

```php
<?php

require_once 'autoload.php';
```

## Sample usage

```php

use MLocati\IMAP\Client;

require_once 'autoload.php'; // Not required if you use composer

// Connect to the mailbox
$client = new Client(
	'MailboxLogin',
    'MailboxPassword',
    'imap.server.com',
    '/tls'
);

// List messages
foreach ($client->getMessages() as $message) {
    echo '#Subject: ', $message->getSubject(), "\n";
    if ($message->getDate() !== null) {
        echo ' Date: ', $message->getDate()->format('c'), "\n";
    }
    echo ' From: ', $message->getFrom(), "\n";
    echo ' To: ', $message->getTo(), "\n";
    echo ' Message ID: ', $message->getID(), "\n";
    echo ' Marked for deletion? ', $message->isDeleted() ? 'yes' : 'no', "\n";
    echo " Total message parts: ", count($message->getAllParts()), "\n";
    printPart($message->getRootPart(), 0);

    if ($message->getSubject() == 'Hi!') {
        $message->delete();
    } elseif ($message->isDeleted()) {
        $message->undelete();
    }
}

// Delete all messages marked for deletion.
$client->expunge();

function printPart(MLocati\IMAP\MessagePart $part, $deepLevel)
{
    $indent = str_repeat("  ", $deepLevel + 1);
    echo $indent, "#ContentType: ", $part->getFullType(), "\n";
    echo $indent, " Name: ", $part->getName(), "\n";
    echo $indent, " Description: ", $part->getDescription(), "\n";
    echo $indent, " Disposition: ", $part->getDisposition(), "\n";
    echo $indent, " DispositionName: ", $part->getDispositionName(), "\n";
    echo $indent, " Content: ", strlen($part->getContents()), " bytes\n";
    $children = $part->getParts();
    if (empty($children)) {
        echo $indent, " No child parts\n";
    } else {
        echo $indent, " ", count($children)," child part(s):\n";
        foreach ($children as $child) {
            printPart($child, $deepLevel + 1);
        }
    }
}
```

Sample output:

```
#Subject: Sample message
 Date: 2016-11-02T13:31:40+01:00
 From: Sender Name <sender@address.com>
 To: recipient@address.com
 Message ID: <12345@address.com>
 Marked for deletion? yes
 Total message parts: 1
  #ContentType: text/html
   Name: 
   Description: 
   Disposition: 
   DispositionName: 
   Content: 1129 bytes
   No child parts
#Subject: This is "à" <t>èst
 Date: 2016-11-16T08:30:08+01:00
 From: second.sender@address.com
 To: recipient@address.com
 Message ID: <67890@address.com>
 Marked for deletion? no
 Total message parts: 8
  #ContentType: multipart/mixed
   Name: 
   Description: 
   Disposition: 
   DispositionName: 
   Content: 0 bytes
   3 child part(s):
    #ContentType: multipart/alternative
     Name: 
     Description: 
     Disposition: 
     DispositionName: 
     Content: 0 bytes
     2 child part(s):
      #ContentType: text/plain
       Name: 
       Description: 
       Disposition: 
       DispositionName: 
       Content: 198 bytes
       No child parts
      #ContentType: multipart/related
       Name: 
       Description: 
       Disposition: 
       DispositionName: 
       Content: 0 bytes
       2 child part(s):
        #ContentType: text/html
         Name: 
         Description: 
         Disposition: 
         DispositionName: 
         Content: 813 bytes
         No child parts
        #ContentType: image/png
         Name: Test.png
         Description: 
         Disposition: inline
         DispositionName: Test.png
         Content: 3118 bytes
         No child parts
    #ContentType: application/octet-stream
     Name: Test.7z
     Description: 
     Disposition: attachment
     DispositionName: Test.7z
     Content: 2831 bytes
     No child parts
    #ContentType: application/zip
     Name: Test.zip
     Description: 
     Disposition: attachment
     DispositionName: Test.zip
     Content: 2891 bytes
     No child parts
```
 