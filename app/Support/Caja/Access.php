<?php

namespace App\Support\Caja;

use App\Models\Arqueo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class Access
{
    public static function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new HttpException(401, 'Se requiere autenticacion');
        }
        if (mb_strtolower(trim($user->estado_usuario)) !== 'activo') {
            throw new HttpException(403, 'El usuario no esta activo');
        }

        return $user;
    }

    public static function admin(Request $request): bool
    {
        return mb_strtolower(trim(self::user($request)->rol_usuario)) === 'administrador';
    }

    public static function requireAdmin(Request $request): void
    {
        if (! self::admin($request)) {
            throw new HttpException(403, 'Se requiere un administrador');
        }
    }

    public static function agency(User $user): ?int
    {
        return $user->trabajadores()->value('id_subagencia');
    }

    public static function days(Request $request): Builder
    {
        $query = Arqueo::query();
        if (! self::admin($request)) {
            $query->where('id_usuario', self::user($request)->id);
        }

        return $query;
    }

    public static function day(Request $request, int $id, bool $lock = false): Arqueo
    {
        $query = self::days($request);

        return ($lock ? $query->lockForUpdate() : $query)->findOrFail($id);
    }

    public static function check(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
