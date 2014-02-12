<?php

/**
 * MyRadio_SpecialFormFieldConstructor class.
 *
 * @version 20140113
 * @author  Matt Windsor <matt.windsor@ury.org.uk>
 * @package MyRadio_Core
 */

/**
 * A method object that constructs a special form field given a name and array
 * representation of its specification.
 *
 * @version 20140113
 * @author  Matt Windsor <matt.windsor@ury.org.uk>
 * @package MyRadio_Core
 */
class MyRadio_SpecialFormFieldConstructor extends MyRadio_FormFieldConstructor
{
    /**
     * Builds and attaches the field.
     *
     * @return null Nothing.
     */
    public function make()
    {
        $done = $this->dispatchSpecialFieldHandler();
        if (!$done) {
            throw new MyRadioException(
                'Illegal special field name: ' . $this->name . '.'
            );
        }
    }

    /**
     * Attempts to handle the special field by dispatching on its name.
     *
     * @return boolean True if the field was handled; false otherwise.
     */
    private function dispatchSpecialFieldHandler()
    {
        $matches = [];
        // TODO: Replace regexes with something a bit less awful.
        $operators = [
            '/^!repeat\( *([0-9]+) *, *([0-9]+) *\)$/' => 'addRepeatedFieldsToForm',
            '/^!section\((.*)\)$/' => 'addSectionToForm'
        ];
        $done = false;

        foreach ($operators as $regex => $callback) {
            if (preg_match($regex, $this->name, $matches)) {
                call_user_func([$this, $callback], $matches);
                $done = true;
            }
        }

        return $done;
    }

    /**
     * Adds a repeated field block to a form.
     *
     * @param array $options The array of options to the !repeat
     *                       directive.  This should contain three
     *                       numeric indices: the repeat directive, the
     *                       repetition ID start, and end.
     *
     * @return Nothing.
     */
    private function addRepeatedFieldsToForm(array $options)
    {
        $start = intval($options[1]);
        $end = intval($options[2]);

        if ($start >= $end) {
            throw new MyRadioException(
                'Start and end wrong way around on !repeat: start='
                . $start
                . ', end='
                . $end
                . '.'
            );
        }
        for ($i = $start; $i <= $end; $i++) {
            $this->addRepeatedFieldsInstanceToForm($i);
        }
    }

    /**
     * Adds an instance of a repeated field block to a form.
     *
     * The fields inside the block have the variable 'repeater' bound to the
     * current iteration of the repeater.
     *
     * @param int $iteration The current iteration of the repeater.
     *
     * @return null Nothing.
     */
    private function addRepeatedFieldsInstanceToForm($iteration)
    {
        foreach ($this->field as $name => $infield) {
            $new_bindings = array_merge(
                ['repeater' => strval($iteration)],
                $this->bindings
            );
            $new_name = $name . $iteration;
            $this->fc->addFieldToForm($new_name, $infield, $new_bindings);
        }
    }

    /**
     * Adds a section to a form.
     *
     * @param array $options The array of options to the !section directive.
     *                       This should contain one numeric index: the
     *                       section name.
     *
     * @return null Nothing.
     */
    private function addSectionToForm(array $options)
    {
        $this->addSectionHeader($options[1]);
        $this->addSectionBody();
    }

    /**
     * Adds a section header to a form.
     *
     * @param string $name The human-readable name of the section.
     *
     * @return null Nothing.
     */
    private function addSectionHeader(/* string */ $name)
    {
        $this->fc->addFieldToForm(
            $this->sectionHeaderName($name),
            [
                'type' => 'section',
                'label' => $name,
                'options' => []
            ],
            $this->bindings
        );
    }

    /**
     * Adds a section body to a form.
     *
     * @return null Nothing.
     */
    private function addSectionBody()
    {
        foreach ($this->field as $name => $infield) {
            $this->fc->addFieldToForm($name, $infield, $this->bindings);
        }
    }

    /**
     * Generates a valid name for a section header form field.
     *
     * @return string $name  A name that should be unique to the section, but
     *                contains no invalid characters.
     */
    private function sectionHeaderName($name)
    {
        return base64_encode($name);
    }
}
