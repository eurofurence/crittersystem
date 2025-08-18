<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Http\Response;
use Engelsystem\Models\AngelType;

class AngelTypesController extends BaseController
{
    public function __construct(protected Response $response)
    {
    }

    public function about(): Response
    {
        $query = AngelType::query();
        if (!auth()->can('user.type.staff')) {
            $query->where('staff_only', false);
        }
        $angeltypes = $query->get();

        return $this->response->withView(
            'pages/angeltypes/about',
            ['angeltypes' => $angeltypes]
        );
    }
}
