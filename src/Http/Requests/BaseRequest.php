<?php
namespace RahatulRabbi\TalkBridge\Http\Requests;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use RahatulRabbi\TalkBridge\Traits\ApiResponse;
class BaseRequest extends FormRequest {
    use ApiResponse;
    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(
            $this->error($validator->errors(), $validator->errors()->first(), 422)
        );
    }
}
