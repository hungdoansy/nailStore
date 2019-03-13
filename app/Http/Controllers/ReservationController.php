<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Webklex\IMAP\Client;

class ReservationController extends Controller
{
    public function getReservations() {
        $mbox = imap_open ("{imap.gmail.com:993/imap/ssl}INBOX", "labella.collector@gmail.com", "uppsala123");
        if($mbox === false){
            //If it failed, throw an exception that contains
            //the last imap error.
            return "loooix cmnr";
        }
        $search = 'SINCE "' . date("j F Y", strtotime("-1 days")) . '"';
        $emails = imap_search($mbox, $search);
//        $folders = imap_listmailbox($mbox, "{imap.gmail.com:993/imap/ssl}", "*");
        $email_list = array();
        if(!empty($emails)){
            //Loop through the emails.
            foreach($emails as $email){
                //Fetch an overview of the email.
                $overview = imap_fetch_overview($mbox, $email);
                $overview = $overview[0];

                $email_number = $overview->msgno;
                $inserted_record = DB::table("online_reservations")->where('mail_number', $email_number)->first();
                if ($inserted_record) {
                    continue;
                }

                $header = imap_headerinfo($mbox,$email_number);
                $from_host = $header->from[0]->host;
                if ($from_host != "bokadirekt.se") continue;




                $message = (imap_fetchbody($mbox,$email_number,1.1));
                if($message == '')
                {
                    $message = (imap_fetchbody($mbox,$email_number,1));
                }
//                dd(mb_detect_encoding($message , 'UTF-16, UTF-8, UTF-7, ASCII, EUC-JP,SJIS, eucJP-win, SJIS-win, JIS, ISO-2022-JP'));
                $message = imap_qprint($message);
//                $message = str_replace("=", "\u00", $message);
//                $message = utf8_encode($message);
//                $message = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
//                    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UTF-16BE');
//                }, $message);
////                dd(($message));

                $lines = explode("\r\n", $message);
//                dd($lines);
//                foreach ($lines as $line) {
//                    $line = utf8_encode($line);
//                    dd($line);
//                }
                $subject = strtolower($header->subject);
                if ($subject == "ny bokning") {
                    $this->handleNewBookingMail($lines, $email_number);
                    continue;
                } else if (strpos($subject, 'avbokning') !== false){
                    $this->handleCancelBookingMail($lines, $email_number);
                    continue;
                } else continue;

                array_push($email_list, $message);


            }
        }
        imap_close($mbox);
        dd($email_list);

    }

    public function handleNewBookingMail($lines, $email_number) {
//        dd($lines);
        $mail_type = "book";
        $customer_name = $customer_email = $customer_mobile = $customer_telephone
            = $customer_booking_time = $customer_duration = $customer_service = $customer_notice = "";
        foreach ($lines as $line) {
            if (strpos($line, 'Kundens namn') !== false) {
                $customer_name = json_encode(mb_strtolower(trim(str_replace("Kundens namn: ", "", $line), "\t ")));
                echo $customer_name."<br>";
                continue;
            }
            if (strpos($line,   'Mobil') !== false) {
                $customer_mobile = trim(str_replace("Mobil: ", "", $line), "\t ");
                continue;
            }
            if (strpos($line, 'Telefon') !== false) {
                $customer_telephone = trim(str_replace("Telefon: ", "", $line), "\t ");
                continue;
            }
            if (strpos($line, 'Epost') !== false) {
                $customer_email = trim(str_replace("Epost: ", "", $line), "\t ");
                continue;
            }
            if (strpos($line, 'Tidpunkt') !== false) {
                $customer_booking_time = trim(str_replace("Tidpunkt: ", "", $line), "\t ").":00";
                continue;
            }
            if (strpos($line, 'fyllning') !== false) {
                $customer_duration = (substr($line, -6, 2));
                if (strpos($line, 'Nagel') !== false) {
                    $customer_service = "Nagel";
                    continue;
                }
                if (strpos($line, 'Singel') !== false) {
                    $customer_service = "Singel";
                    continue;
                }
                if (strpos($line, 'Manikyr') !== false) {
                    $customer_service = "Manikyr";
                    continue;
                }
            }
            if (strpos($line, 'meddelande') !== false) {
                $customer_notice = trim(str_replace("Ev. meddelande: ", "", $line), "\t ");
                continue;
            }

        }

//        dd($customer_name, $customer_service, $customer_mobile, $customer_telephone, $customer_email, $customer_notice, $customer_booking_time, $customer_duration);

        DB::table("online_reservations")->insert([
            'mobile' => $customer_mobile,
            'telephone' => $customer_telephone,
            'email' => $customer_email,
            'reservation_time' => $customer_booking_time,
            'customer_name' => $customer_name,
            'type' => $mail_type,
            'duration' => $customer_duration,
            'service_type' => $customer_service,
            'notice' => $customer_notice,
            'mail_number' => $email_number,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function handleCancelBookingMail($lines, $email_number) {
//        dd(strpos($line, 'tjänst'));
//        dd($lines);
        $mail_type = "cancel";
        $customer_name = $customer_email = $customer_mobile = $customer_telephone
            = $customer_booking_time = $customer_duration = $customer_service = $customer_notice = "";
        foreach ($lines as $line) {
            if (strpos($line, 'Kundens namn') !== false) {
                $customer_name = json_encode(mb_strtolower(trim(str_replace("Kundens namn: ", "", $line), "\t ")));
                echo $customer_name."<br>";
                continue;
            }
            if (strpos($line, 'Mobil') !== false) {
                $customer_mobile = trim(str_replace("Mobil: ", "", $line), "\t ");
                continue;
            }
            if (strpos($line, 'Telefon') !== false) {
                $customer_telephone = trim(str_replace("Telefon: ", "", $line), "\t ");
                continue;
            }
            if (strpos($line, 'Epost') !== false) {
                $customer_email = trim(str_replace("Epost: ", "", $line), "\t ");
                continue;
            }
            if (strpos($line, 'har avbokats') !== false) {
//                $customer_duration = (substr($line, -6, 2));
                $customer_booking_time = substr(explode("fyllning, ", $line)[1], 0, 16).":00";
                if (strpos($line, 'Nagel') !== false) {
                    $customer_service = "Nagel";
                    continue;
                }
                if (strpos($line, 'Singel') !== false) {
                    $customer_service = "Singel";
                    continue;
                }
                if (strpos($line, 'Manikyr') !== false) {
                    $customer_service = "Manikyr";
                    continue;
                }

            }
            if (strpos($line, 'Avbokningsmeddelande') !== false) {
                $customer_notice = trim(str_replace("Avbokningsmeddelande: ", "", $line), "\t ");
//                dd($customer_notice);
                continue;
            }
        }
//        dd($customer_name, $customer_service, $customer_mobile, $customer_telephone, $customer_email, $customer_notice, $customer_booking_time);

        DB::table("online_reservations")->where([
            ['customer_name', '=', $customer_name],
            ['reservation_time', '=', $customer_booking_time],
            ['service_type', '=', $customer_service],
            ['type', '=', "book"]
        ])->update(["deleted_at" => date('Y-m-d H:i:s')]);

        DB::table("online_reservations")->insertGetId([
            'mobile' => $customer_mobile,
            'telephone' => $customer_telephone,
            'email' => $customer_email,
            'reservation_time' => $customer_booking_time,
            'customer_name' => $customer_name,
            'type' => $mail_type,
            'duration' => $customer_duration,
            'service_type' => $customer_service,
            'notice' => $customer_notice,
            'mail_number' => $email_number,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
