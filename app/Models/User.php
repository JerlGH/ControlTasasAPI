<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens,HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario',
        'contraseña',
        'rol_usuario',
        'correo_asignado_cajero',
        'usuario_asignado_airpak',
        'estado_usuario',
        'id_trabajador',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'contraseña',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'contraseña' => 'hashed',
        ];
    }

    public function trabajadores(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class,);
    }
    

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'id_usuario','id');
    }

    public function mesas_de_cambio(): HasMany
    {
        return $this->hasMany(Mesa_De_Cambio::class, 'id_usuario','id');
    }
    

}
