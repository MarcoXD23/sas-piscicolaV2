<?php

namespace App\Http\Requests;

use App\Models\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignarRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->tieneRol(Rol::PROPIETARIO);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::in(Rol::ASIGNABLES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.required' => 'Selecciona al menos un rol.',
            'roles.min' => 'Selecciona al menos un rol.',
            'roles.*.in' => 'Rol no permitido.',
        ];
    }
}

