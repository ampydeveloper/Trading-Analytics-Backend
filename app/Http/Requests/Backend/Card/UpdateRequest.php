<?php

namespace App\Http\Requests\Backend\Card;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Class UpdateRequest.
 */
class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'player' => ['required'],
            'year' => ['required'],
            'brand' => ['required'],
            'card' => ['required'],
            'rc' => ['required'],
            'variation' => ['required'],
            'qualifiers' => ['required'],
        ];
    }
}
