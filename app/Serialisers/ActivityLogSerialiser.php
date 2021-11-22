<?php 
namespace App\Serialisers;

use Illuminate\Database\Eloquent\Model;
use Cyberduck\LaravelExcel\Contract\SerialiserInterface;
use App\Models\Auth\User;

class ActivityLogSerialiser implements SerialiserInterface
{
    public function getData($data)
    {
//        dd($data->properties);
        $row = [];

        $row[] = $data->id;
        $row[] = $data->description;
        $row[] = $data->subject_type;
        $row[] = $data->subject_id;
        $subject_name_val = "";
        $subject_name = User::where('id',$data->subject_id)->select("first_name","last_name")->first();
        if(!empty($subject_name)){
           $subject_name_val =  $subject_name['full_name'];
        }
        $row[] = $subject_name_val;
   
        $operation = "";
        if(!empty($data->properties)) {
            $decode = json_decode($data->properties, true);
            if($data->subject_type =="App\Models\RequestListing") {
                if(!empty($data->description =="created")) {
                    $operation = "Listing Submitted";
                } else {
                    if($decode['attributes']['approved'] == 1){
                        $operation = "Listing Approved";
                    } elseif($decode['attributes']['approved'] == '-1') {
                        $operation = "Listing Declined";
                    }
                }
            } else if ($data->subject_type =="App\Models\RequestSlab") {
                if(!empty($data->description =="created")) {
                    $operation = "Listing Submitted";
                } else {
                    if($decode['attributes']['status'] == 1) {
                        $operation = "Listing Approved";
                    } elseif($decode['attributes']['status'] == '-1') {
                        $operation = "Listing Declined";
                    }
                }
            }
        }
        $row[] = $operation;
        $row[] = Date("F d Y - h:m:s", strtotime($data->created_at));

        return $row;
    }

    public function getHeaderRow()
    {
        return [
            'ID',
            'Action',
            'Entity',
            'Entity ID',
            'Username',
            'Operation',
            'Date/Time'
        ];
    }
}