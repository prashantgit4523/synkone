<?php

namespace App\Models\DocumentAutomation;

use App\Helpers\SystemGeneratedDocsHelpers;
use App\Models\Compliance\Project;
use App\Models\Compliance\ProjectControl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $fillable = ['name', 'body', 'description'];

    /*
     * returns all the versions available for that template
     * table: control_documents
     */
    public function versions()
    {
        return $this->hasMany(ControlDocument::class);
    }

    public function latest()
    {
        $sys_docs = SystemGeneratedDocsHelpers::getSystemGeneratedDocuments();
        $generated = array_key_exists($this->name, $sys_docs) && $sys_docs[$this->name][1]();

        $control = ProjectControl::firstWhere('document_template_id', $this->id);

        $project = null;
        if($control){
            $project = $control->project;
        }

        return $this
            ->hasOne(ControlDocument::class)
            ->orderByDesc('id')
            ->when($this->versions()->where('status', 'published')->exists(), function ($q) {
                $q->where('status', 'published');
            })
            ->withDefault([
                'title' => $this->name,
                'description' => $this->description,
                'body' => $this->body,
                'version' => $generated ? '1.0' : '0.1',
                'status' => $generated ? 'published' : 'draft',
                'project_created_at' => $project ? $project->created_at->format('d/m/Y') : null,
            ]);
    }

    public function published()
    {
        return $this->hasOne(ControlDocument::class)->published();
    }
}
