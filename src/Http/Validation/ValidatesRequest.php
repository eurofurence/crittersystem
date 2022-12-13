<?php

namespace Engelsystem\Http\Validation;

use Engelsystem\Http\Exceptions\ValidationException;
use Engelsystem\Http\Request;

trait ValidatesRequest
{
    /** @var Validator */
    protected $validator;

    /**
     * @param array   $rules
     * @return array
     */
    protected function validate(Request $request, array $rules)
    {
        $isValid = $this->validator->validate(
            (array)$request->getParsedBody(),
            $rules
        );

        if (!$isValid) {
            throw new ValidationException($this->validator);
        }

        return $this->validator->getData();
    }

    public function setValidator(Validator $validator)
    {
        $this->validator = $validator;
    }
}
