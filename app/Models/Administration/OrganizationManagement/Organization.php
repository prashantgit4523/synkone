<?php

namespace App\Models\Administration\OrganizationManagement;

use App\Casts\CustomCleanHtml;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Administration\OrganizationManagement\Department;
use \Mews\Purifier\Casts\CleanHtml;

class Organization extends Model
{
    use HasFactory;

    protected $table = 'organizations';

    protected $casts = [
        'name'    => CustomCleanHtml::class,
    ];

    public function departments()
    {
        return $this->hasMany(Department::class, 'organization_id');
    }


    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return OrganizationFactory::new();
    }
}
