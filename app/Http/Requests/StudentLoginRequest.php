<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ['matricule' => ['required', 'string', 'max:80']];

        if (config('uist2its.student_second_factor')) {
            $rules['date_naissance'] = ['required', 'date_format:Y-m-d'];
        }

        return $rules;
    }
}
