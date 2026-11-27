<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class DangNhapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('dia_chi_thu_dien_tu'))) {
            $this->merge(['dia_chi_thu_dien_tu' => Str::lower(trim($this->input('dia_chi_thu_dien_tu')))]);
        }
    }

    public function rules(): array
    {
        return [
            'dia_chi_thu_dien_tu' => ['required', 'string', 'email:rfc', 'max:190'],
            'password' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('Mật khẩu quá dài. Vui lòng dùng tối đa 72 byte (chữ có dấu có thể chiếm nhiều byte).');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'dia_chi_thu_dien_tu.required' => 'Vui lòng nhập email.',
            'dia_chi_thu_dien_tu.string' => 'Email không hợp lệ.',
            'dia_chi_thu_dien_tu.email' => 'Email không đúng định dạng.',
            'dia_chi_thu_dien_tu.max' => 'Email không được quá 190 ký tự.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.string' => 'Mật khẩu không hợp lệ.',
            'password.max' => 'Mật khẩu quá dài.',
        ];
    }
}
