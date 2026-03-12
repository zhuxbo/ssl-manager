<?php

namespace App\Models\Acme;

use App\Models\BaseModel;
use App\Models\Chain;
use App\Models\Traits\HasSnowflakeId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcmeCert extends BaseModel
{
    use HasFactory, HasSnowflakeId;

    private ?string $cachedIntermediateCert = null;

    private bool $intermediateCertCached = false;

    protected $table = 'acme_certs';

    protected $fillable = [
        'order_id',
        'last_cert_id',
        'action',
        'channel',
        'api_id',
        'vendor_id',
        'refer_id',
        'common_name',
        'alternative_names',
        'email',
        'standard_count',
        'wildcard_count',
        'validation_method',
        'validation',
        'params',
        'amount',
        'csr_md5',
        'csr',
        'private_key',
        'cert',
        'intermediate_cert',
        'serial_number',
        'issuer',
        'fingerprint',
        'encryption_alg',
        'encryption_bits',
        'signature_digest_alg',
        'cert_apply_status',
        'domain_verify_status',
        'issued_at',
        'expires_at',
        'status',
        'auto_deploy_at',
    ];

    protected $casts = [
        'params' => 'json',
        'validation' => 'json',
        'amount' => 'decimal:2',
        'standard_count' => 'integer',
        'wildcard_count' => 'integer',
        'encryption_bits' => 'integer',
        'cert_apply_status' => 'integer',
        'domain_verify_status' => 'integer',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'auto_deploy_at' => 'datetime',
    ];

    protected $appends = ['intermediate_cert'];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->csr_md5 = md5($model->csr ?? '');
            if (empty($model->refer_id)) {
                $model->refer_id = bin2hex(random_bytes(16));
            }
        });

        static::retrieved(function ($model) {
            if ($model->status === 'active' && ! empty($model->issuer)) {
                $model->cachedIntermediateCert = Chain::where('common_name', $model->issuer)->value('intermediate_cert');
                $model->intermediateCertCached = true;

                if (empty($model->cachedIntermediateCert)) {
                    $model->attributes['status'] = 'approving';
                }
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AcmeOrder::class);
    }

    public function acmeAuthorizations(): HasMany
    {
        return $this->hasMany(Authorization::class, 'cert_id');
    }

    public function lastCert(): BelongsTo
    {
        return $this->belongsTo(self::class, 'last_cert_id');
    }

    public function getIntermediateCertAttribute(): ?string
    {
        if (empty($this->issuer)) {
            return null;
        }

        if ($this->intermediateCertCached) {
            return $this->cachedIntermediateCert;
        }

        $this->cachedIntermediateCert = Chain::where('common_name', $this->issuer)->value('intermediate_cert');
        $this->intermediateCertCached = true;

        return $this->cachedIntermediateCert;
    }

    public function setIntermediateCertAttribute(?string $value): void
    {
        if (empty($this->issuer) || empty($value)) {
            return;
        }

        $chain = Chain::where('common_name', $this->issuer)->first();

        if (! $chain) {
            Chain::create([
                'common_name' => $this->issuer,
                'intermediate_cert' => $value,
            ]);
        }
    }
}
