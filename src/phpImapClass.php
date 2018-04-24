<?php

Class phpImap
{
    //---Configuration -----------------------
    private $server   = 'imap.gmail.com';
    private $user     = '';
    private $pass     = '';
    private $folder   = 'INBOX';
    //---End Config --------------------------

    private $mbox;
    private $emails;
    public  $header;
    public  $timestamp;
    public  $subject;
    public  $from;
    public  $fromname;
    public  $fromemail;
    public  $body;
    public  $attachments;

    public function __construct($server = null,
                                $user = null, $pass = null, $folder = null)
    {
        if ($server) $this->server = $server;
        if ($user)   $this->user   = $user;
        if ($pass)   $this->pass   = $pass;
        if ($folder) $this->folder = $folder;

        $mailbox = '{'.$this->server.':993/imap/ssl/novalidate-cert}'.$this->folder;
        $this->mbox = imap_open($mailbox, $this->user, $this->pass);
    }

    public function close()
    {
        imap_close($this->mbox);
    }

    public function loadEmails($criteria = 'ALL', $descending = true)
    {
        $emails = imap_search($this->mbox, $criteria);
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

    public function listEmails()
    {
        echo '<style>
        table {border-collapse: collapse; margin: auto;}
        td {border: 1px solid black; padding: 0px 6px;}
        </style>';
        echo '<table>';
        echo '<tr><th>Num</th><th>Date</th><th>Att</th><th>From name</th>
                <th>From email</th><th>Subject</th></tr>';
        foreach($this->emails as $num) {
            $this->loadHeader($num);
            $att = $this->hasAttachments($num);
            echo '<tr><td>'.$num.'</td>';
            echo '<td>'.date('Y-m-d', $this->timestamp).'</td>';
            echo '<td>'.$att.'</td>';
            echo '<td>'.$this->fromname.'</td>';
            echo '<td>'.$this->fromemail.'</td>';
            echo '<td>'.$this->subject.'</td></tr>';
        }
        echo '</table>';
    }

    public function displayEmail($num)
    {
        $this->loadHeader($num);
        $this->loadBody($num);
        echo '<b>'.date('Y-m-d', $this->timestamp).' : ';
        echo $this->subject.'<br>';
        echo $this->fromname.' : ';
        echo $this->fromemail.'</b><br>';
        echo $this->body.'<hr>';
    }

    public function loadHeader($num)
    {
        $this->header = imap_headerinfo($this->mbox, $num);
        $this->timestamp = $this->header->udate;
        if (isset($this->header->subject))
            $this->subject = imap_utf8($this->header->subject);
        else
            $this->subject = 'No Subject';
        $this->from      = $this->header->from[0];
        if (isset($this->from->personal))
            $this->fromname = imap_utf8($this->from->personal);
        else
            $this->fromname = 'No Name';
        $this->fromemail = $this->from->mailbox.'@'.$this->from->host;
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

    public function loadAttachments($num, $dir = false)
    {
        $this->attachments = array();
        if ($dir && !is_dir($dir))
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
                if ($dir) {
                    file_put_contents($dir.'/'.$fname, $file);
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
