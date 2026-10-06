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
            'roles.required' => __('mensajes.asignar_roles.selecciona_menos_rol'),
            'roles.min' => __('mensajes.asignar_roles.selecciona_menos_rol'),
            'roles.*.in' => __('mensajes.asignar_roles.rol_no_permitido'),
        ];
    }
}
