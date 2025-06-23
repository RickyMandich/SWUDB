<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model for system errors tracking and management
 * Modello per il tracciamento e la gestione degli errori di sistema
 *
 * This model stores all system errors that occur on the platform,
 * allowing administrators to track, manage, and resolve issues.
 */
class SystemError extends Model
{
    use HasFactory;

    protected $fillable = [
        'exception_class',
        'message',
        'file',
        'line',
        'trace',
        'request_url',
        'request_method',
        'user_agent',
        'user_id',
        'status',
        'admin_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who encountered this error
     * Ottiene l'utente che ha riscontrato questo errore
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin user who resolved this error
     * Ottiene l'utente admin che ha risolto questo errore
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope to get only new/unresolved errors
     * Scope per ottenere solo errori nuovi/non risolti
     */
    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', ['new', 'in_progress']);
    }

    /**
     * Scope to get only resolved errors
     * Scope per ottenere solo errori risolti
     */
    public function scopeResolved($query)
    {
        return $query->whereIn('status', ['resolved', 'ignored']);
    }

    /**
     * Get status badge color for UI display
     * Ottiene il colore del badge di stato per la visualizzazione UI
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match($this->status) {
            'new' => 'danger',
            'in_progress' => 'warning',
            'resolved' => 'success',
            'ignored' => 'secondary',
            default => 'secondary'
        };
    }

    /**
     * Get status display text
     * Ottiene il testo di visualizzazione dello stato
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'new' => 'Nuovo',
            'in_progress' => 'In Lavorazione',
            'resolved' => 'Risolto',
            'ignored' => 'Ignorato',
            default => 'Sconosciuto'
        };
    }

    /**
     * Get short file path for display
     * Ottiene il percorso file abbreviato per la visualizzazione
     */
    public function getShortFileAttribute(): string
    {
        $basePath = base_path();
        return str_replace($basePath, '', $this->file);
    }

    /**
     * Mark error as resolved by admin
     * Segna l'errore come risolto da un admin
     */
    public function markAsResolved(User $admin, string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
            'admin_notes' => $notes,
        ]);
    }

    /**
     * Mark error as ignored by admin
     * Segna l'errore come ignorato da un admin
     */
    public function markAsIgnored(User $admin, string $notes = null): void
    {
        $this->update([
            'status' => 'ignored',
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
            'admin_notes' => $notes,
        ]);
    }

    /**
     * Mark error as in progress by admin
     * Segna l'errore come in lavorazione da un admin
     */
    public function markAsInProgress(User $admin, string $notes = null): void
    {
        $this->update([
            'status' => 'in_progress',
            'resolved_by' => $admin->id,
            'admin_notes' => $notes,
        ]);
    }
}
