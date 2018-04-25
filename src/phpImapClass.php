<?php

Class phpImap
{
    //--- Configuration -----------------------
    private $host   = 'imap.gmail.com';
    private $user   = '';
    private $pass   = '';
    private $folder = 'INBOX';
    //--- End Config --------------------------
    private $port   = '993';
    private $flags  = '/imap/ssl/novalidate-cert';

    private $mbox;
    private $emails = array();
    public  $header;
    public  $timestamp;
    public  $subject;
    public  $fromname;
    public  $fromemail;
    public  $body;
    public  $attachments;

    public function __construct($host = null, $user = null, $pass = null,
                                $folder = null)
    {
        if ($host)   $this->host   = $host;
        if ($user)   $this->user   = $user;
        if ($pass)   $this->pass   = $pass;
        if ($folder) $this->folder = $folder;
        $this->folder = imap_utf8_to_mutf7($folder);

        $mailbox = '{'.$this->host.':'.$this->port.$this->flags.'}'.$this->folder;
        $this->mbox = @imap_open($mailbox, $this->user, $this->pass);
        if (!$this->mbox)
            exit(imap_last_error());        
    }

    public function close()
    {
        imap_close($this->mbox);
    }

    public function listFolders()
    {
        $hoststr = '{'.$this->host.'}';
        echo '<b>'.$hoststr.'</b> folders:<br>';
        $list = imap_list($this->mbox, $hoststr, '*');
        $newlist = array();
        foreach($list as $key => $folder) {
            $newlist[] = str_replace($hoststr, '', imap_mutf7_to_utf8($folder));
        }
        foreach($newlist as $item)
            echo '&nbsp;&nbsp;'.$item.'<br>';
    }

    public function loadEmails($criteria = 'ALL', $descending = true)
    {
        $emails = imap_search($this->mbox, $criteria);
            if (!$emails)
                exit('Invalid mailbox. Can not load emails.');
        if ($descending === true)
            $this->emails = array_reverse($emails);
        else
            $this->emails = $emails;
        return $this->emails;
    }

    public function showEmails($directory = false)
    {
        foreach($this->emails as $num) {
            $this->displayEmail($num);
            if ($directory)
                $this->loadAttachments($num, $directory);
        }
    }

    public function overView()
    {
?>
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                table {border-collapse: collapse; margin: auto;}
                td    {border: 1px solid black; padding: 0px 6px;}
            </style>
        </head>
        <body>
            <table>
            <tr><th>Nr</th><th>Date</th><th>Att</th><th>From name</th>
                    <th>From email</th><th>Subject</th></tr>
<?php
            foreach($this->emails as $num) {
                $this->loadHeader($num);
                $att = $this->hasAttachments($num);
                echo '<tr><td>'.$num.'</td>';
                echo '<td>'.date('Y-m-d', $this->timestamp).'</td>';
                echo '<td>'.$att.'</td>';
                echo '<td>'.$this->fromname.'</td>';
                echo '<td>'.$this->fromemail.'</td>';
                echo '<td>'.$this->subject.'</td></tr>'."\n";
            }
?>
            </table>
        </body>
        </html>

<?php
    }

    public function downloadAttachments($directory)
    {
        if (!is_dir($directory))
            exit('Error: Invalid attachments directory');
        foreach($this->emails as $num) {
            if ($this->hasAttachments($num)) {
                $this->loadAttachments($num, $directory);
            }
        }
        echo 'Success!<br>
            Download completed into <b>"'.$directory.'"</b> directory.';
    }

    public function displayEmail($num)
    {
        $this->loadHeader($num);
        $this->loadBody($num);
        echo '<b>'.date('Y-m-d', $this->timestamp).' : ';
        echo $this->subject.'<br>';
        echo $this->fromname.' : ';
        echo $this->fromemail.'</b><br>'."\n";
        echo $this->body.'<hr>'."\n\n";
    }

    public function loadHeader($num)
    {
        $this->header = imap_headerinfo($this->mbox, $num);
        $this->timestamp = $this->header->udate;
        if (isset($this->header->subject))
            $this->subject = imap_utf8($this->header->subject);
        else
            $this->subject = 'No Subject';
        $from = $this->header->from[0];
        if (isset($from->personal))
            $this->fromname = imap_utf8($from->personal);
        else
            $this->fromname = 'No Name';
        $this->fromemail = $from->mailbox.'@'.$from->host;
        return $this->header;
    }

    public function loadBody($num)
    {
        $body = $this->get_part($num, "TEXT/HTML");
        if ($body == "")
            $body = $this->get_part($num, "TEXT/PLAIN");
        $this->body = $body;
        return $this->body;
    }

    public function loadAttachments($num, $directory = false)
    {
        $this->attachments = array();
        if ($directory && !is_dir($directory))
            exit('Error: Invalid attachments directory');
        $struct = imap_fetchstructure($this->mbox, $num);
        if (isset($struct->parts)) {
            $att = count($struct->parts);
            if($att >=2) {
                for($a=0; $a<$att; $a++) {
                    if (isset($struct->parts[$a]->disposition)) {
                        if(strtoupper($struct->parts[$a]->disposition) == 'ATTACHMENT') {
                $file = imap_base64(imap_fetchbody($this->mbox, $num, $a+1));
                if (isset($struct->parts[$a]->dparameters[1]))
                    $fname = imap_utf8($struct->parts[$a]->dparameters[1]->value);
                else
                    $fname = imap_utf8($struct->parts[$a]->dparameters[0]->value);
                $fname = uniqid().'_'.$fname;
                $this->attachments[] = 
                    array(
                        'name' => $fname,
                        'file' => $file
                    ); 
                if ($directory) {
                    file_put_contents($directory.'/'.$fname, $file);
                }
                        }
                    }
                }
            }
        }
        return $this->attachments;
    }

    public function hasAttachments($num)
    {
        $numberattachments = 0;
        $struct = imap_fetchstructure($this->mbox, $num);
        if (isset($struct->parts)) {
            $att = count($struct->parts);
            if($att >=2) {
                for($a=0; $a<$att; $a++) {
                    if (isset($struct->parts[$a]->disposition)) {
                        if(strtoupper($struct->parts[$a]->disposition) == 'ATTACHMENT') {
                            $numberattachments++;
                        }
                    }
                }
            }
        }
        return $numberattachments;
    }

    public function loadStructure($num)
    {
        return imap_fetchstructure($this->mbox, $num);
    }

    private function get_part($num, $mimetype, $structure = false, $partNumber = false)
    {
        if (!$structure)
            $structure = imap_fetchstructure($this->mbox, $num);
        if ($structure) {
            if ($mimetype == $this->get_mime_type($structure)) {
                if (!$partNumber) {
                    $partNumber = 1;
                }
                $text = imap_fetchbody($this->mbox, $num, $partNumber);
                switch ($structure->encoding) {
                    case 3:
                        return imap_base64($text);
                    case 4:
                        $charset = $structure->parameters[0]->value;
                        if ($charset == 'UTF-8') return imap_qprint($text);
                        else return utf8_encode(imap_qprint($text));
                    default:
                        return $text;
                }
            }
            // multipart
            if ($structure->type == 1) {
                foreach ($structure->parts as $index => $subStruct) {
                    $prefix = "";
                    if ($partNumber) {
                        $prefix = $partNumber . ".";
                    }
                    $data = $this->get_part($num, $mimetype, $subStruct, $prefix . ($index + 1));
                    if ($data) {
                        return $data;
                    }
                }
            }
        }
        return false;
    }

    private function get_mime_type($structure)
    {
        $primaryMimetype = ["TEXT", "MULTIPART", "MESSAGE", "APPLICATION", "AUDIO", "IMAGE", "VIDEO", "OTHER"];
        if ($structure->subtype)
            return $primaryMimetype[(int)$structure->type] . "/" . $structure->subtype;
        return "TEXT/PLAIN";
    }
    
}
