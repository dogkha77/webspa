<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DangKyRequest extends FormRequest
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
            'ten_nguoi_dung' => ['required', 'string', 'max:150'],
            'dia_chi_thu_dien_tu' => ['required', 'string', 'email:rfc', 'max:190', Rule::unique('nguoi_dung', 'dia_chi_thu_dien_tu')],
            'so_dien_thoai' => ['nullable', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('Mật khẩu quá dài. Vui lòng dùng tối đa 72 byte (chữ có dấu có thể chiếm nhiều byte).');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'string' => ':attribute phải là văn bản.',
            'max' => ':attribute không được quá :max ký tự.',
            'dia_chi_thu_dien_tu.email' => 'Email không đúng định dạng.',
            'dia_chi_thu_dien_tu.unique' => 'Email đã được sử dụng. Bạn hãy đăng nhập hoặc dùng email khác.',
            'so_dien_thoai.regex' => 'Số điện thoại phải gồm 9–15 chữ số, có thể bắt đầu bằng dấu +.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Mật khẩu nhập lại chưa khớp.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ten_nguoi_dung' => 'họ tên',
            'dia_chi_thu_dien_tu' => 'email',
            'so_dien_thoai' => 'số điện thoại',
            'password' => 'mật khẩu',
        ];
    }
}
