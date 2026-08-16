<?php

namespace App\Http\Controllers\Api;

use App\Models\AdmissionChannel;

class AdmissionChannelController extends MasterDataController
{
    protected string $modelClass = AdmissionChannel::class;

    protected string $nameField = 'channel_name';

    protected int $nameMaxLength = 100;

    protected string $singularLabel = 'admission channel';

    protected string $pluralLabel = 'admission channels';
}
