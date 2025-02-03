<?php

namespace Ichtrojan\Otp;

use Carbon\Carbon;
use Exception;
use Ichtrojan\Otp\Models\Otp as Model;

class Otp
{
    /**
     * @param string $identifier
     * @param string $type
     * @param int $length
     * @param int $validity
     * @return mixed
     * @throws Exception
     */
    // public function generate(string $identifier, string $type, int $length = 4, int $validity = 10) : object
    // {
    //     Model::where('identifier', $identifier)->where('valid', true)->delete();

    //     switch ($type) {
    //         case "numeric":
    //             $token = $this->generateNumericToken($length);
    //             break;
    //         case "alpha_numeric":
    //             $token = $this->generateAlphanumericToken($length);
    //             break;
    //         default:
    //             throw new Exception("{$type} is not a supported type");
    //     }

    //     Model::create([
    //         'identifier' => $identifier,
    //         'token' => $token,
    //         'validity' => $validity
    //     ]);

    //     return (object)[
    //         'status' => true,
    //         'token' => $token,
    //         'message' => 'OTP generated'
    //     ];
    // }
    // public function generate(string $identifier, string $type, int $length = 6, int $validity = 10): object
    // {
    //     Model::where('identifier', $identifier)->where('valid', true)->delete();

    //     switch ($type) {
    //         case "numeric":
    //             $token = $this->generateNumericToken($length); // 6 digits numeric OTP
    //             break;
    //         case "alpha_numeric":
    //             $token = $this->generateAlphanumericToken($length);
    //             break;
    //         default:
    //             throw new Exception("{$type} is not a supported type");
    //     }

    //     Model::create([
    //         'identifier' => $identifier,
    //         'token' => $token,
    //         'validity' => $validity
    //     ]);

    //     return (object)[
    //         'status' => true,
    //         'token' => $token,
    //         'message' => 'OTP generated'
    //     ];
    // }

    public function generate(string $identifier, string $type = 'numeric', int $length = 6, int $validity = 10): object
    {
        // Remove existing valid OTPs for the identifier
        Model::where('identifier', $identifier)->where('valid', true)->delete();

        if ($type !== 'numeric') {
            throw new Exception("{$type} is not a supported type. Only 'numeric' is allowed.");
        }

        // Generate a numeric OTP of the specified length (default: 6 digits)
        $token = $this->generateNumericToken($length);

        // Save OTP to the database
        Model::create([
            'identifier' => $identifier,
            'token' => $token,
            'validity' => $validity,
        ]);

        return (object)[
            'status' => true,
            'token' => $token,
            'message' => 'OTP generated successfully',
        ];
    }



    /**
     * @param string $identifier
     * @param string $token
     * @return mixed
     */
    // public function validate(string $identifier, string $token): object
    // {
    //     $otp = Model::where('identifier', $identifier)->where('token', $token)->first();

    //     if ($otp instanceof Model) {
    //         if ($otp->valid) {
    //             $now = Carbon::now();
    //             $validity = $otp->created_at->addMinutes($otp->validity);

    //             $otp->update(['valid' => false]);

    //             if (strtotime($validity) < strtotime($now)) {
    //                 return (object)[
    //                     'status' => false,
    //                     'message' => 'OTP Expired'
    //                 ];
    //             }

    //             $otp->update(['valid' => false]);

    //             return (object)[
    //                 'status' => true,
    //                 'message' => 'OTP is valid'
    //             ];
    //         }

    //         $otp->update(['valid' => false]);

    //         return (object)[
    //             'status' => false,
    //             'message' => 'OTP is not valid'
    //         ];
    //     } else {
    //         return (object)[
    //             'status' => false,
    //             'message' => 'OTP does not exist'
    //         ];
    //     }
    // }

    public function validate(string $identifier, string $token): object
    {
        $otp = Model::where('identifier', $identifier)->where('token', $token)->first();

        if (!$otp) {
            return (object)[
                'status' => false,
                'message' => 'OTP does not exist.',
            ];
        }

        if (!$otp->valid) {
            return (object)[
                'status' => false,
                'message' => 'OTP is no longer valid.',
            ];
        }

        if (Carbon::now()->greaterThan($otp->created_at->addMinutes($otp->validity))) {
            $otp->update(['valid' => false]);

            return (object)[
                'status' => false,
                'message' => 'OTP has expired.',
            ];
        }

        // Invalidate the OTP after successful validation
        $otp->update(['valid' => false]);

        return (object)[
            'status' => true,
            'message' => 'OTP is valid.',
        ];
    }
    /**
     * @param int $length
     * @return string
     * @throws Exception
     */
    // private function generateNumericToken(int $length = 4): string
    // {
    //     $i = 0;
    //     $token = "";

    //     while ($i < $length) {
    //         $token .= random_int(0, 9);
    //         $i++;
    //     }

    //     return $token;
    // }

    // private function generateNumericToken(int $length = 6): string
    // {
    //     $i = 0;
    //     $token = "";

    //     while ($i < $length) {
    //         $token .= random_int(0, 9);  // Generates a random digit from 0 to 9
    //         $i++;
    //     }

    //     return $token;
    // }

    private function generateNumericToken(int $length = 6): string
    {
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= random_int(0, 9);
        }
        return $token;
    }


    /**
     * @param int $length
     * @return string
     */
    // private function generateAlphanumericToken(int $length = 4): string
    // {
    //     $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
    //     return substr(str_shuffle($characters), 0, $length);
    // }
    private function generateAlphanumericToken(int $length = 6): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle($characters), 0, $length);
    }
}
