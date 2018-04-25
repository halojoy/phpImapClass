### phpImapClass
Easy to use Class for download of Emails from your inbox.<br>
Written in PHP and uses IMAP extension for client.
```
Class phpImap
{
PUBLIC METHODS:
    __construct($host=null, $user=null, $pass=null, $folder=null)
    close($this->mbox)
    listFolders()
    loadEmails($criteria='ALL', $descending=true)
    showEmails($directory=false)
    overView()
    downloadAttachments($directory)
    displayEmail($num)
    loadHeader($num)
    loadBody($num)
    loadAttachments($num, $directory=false)
    hasAttachments($num)
    loadStructure($num)

PRIVATE METHODS:
    getPart()
    get_mime_type()
}
```
