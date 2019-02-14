<?php

namespace Watson\BootstrapForm;

use Illuminate\Support\Str;
use Collective\Html\FormBuilder;
use Collective\Html\HtmlBuilder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Session\SessionManager as Session;
use Illuminate\Contracts\Config\Repository as Config;

class BootstrapForm
{
    use Macroable;

    /**
     * Illuminate HtmlBuilder instance.
     *
     * @var \Collective\Html\HtmlBuilder
     */
    protected $html;

    /**
     * Illuminate FormBuilder instance.
     *
     * @var \Collective\Html\FormBuilder
     */
    protected $form;

    /**
     * Illuminate Repository instance.
     *
     * @var \Illuminate\Config\Repository
     */
    protected $config;

    /**
     * Bootstrap form type class.
     *
     * @var string
     */
    protected $type;

    /**
     * Bootstrap form left column class.
     *
     * @var string
     */
    protected $leftColumnClass;

    /**
     * Bootstrap form left column offset class.
     *
     * @var string
     */
    protected $leftColumnOffsetClass;

    /**
     * Bootstrap form right column class.
     *
     * @var string
     */
    protected $rightColumnClass;

    /**
     * The icon prefix.
     *
     * @var string
     */
    protected $iconPrefix;

    /**
     * The errorbag that is used for validation (multiple forms).
     *
     * @var string
     */
    protected $errorBag;

    /**
     * The error class.
     *
     * @var string
     */
    protected $errorClass;


    /**
     * guessed model
     *
     * @var object
     */
    protected $model;

    /**
     * Construct the class.
     *
     * @param  \Collective\Html\HtmlBuilder             $html
     * @param  \Collective\Html\FormBuilder             $form
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @return void
     */
    public function __construct(HtmlBuilder $html, FormBuilder $form, Config $config)
    {
        $this->html = $html;
        $this->form = $form;
        $this->config = $config;
    }

    /**
     * Check if the user that is logged in has the access to the form
     *
     * @param  string  $elementId
     * @return boolean
     */

    public function isAllowed($elementId){
        return true;
    }

    /**
     * Open a form while passing a model and the routes for storing or updating
     * the model. This will set the correct route along with the correct
     * method.
     *
     * @param  array  $options
     * @return string
     */
    public function open(array $options = [])
    {
        if(isset($options['form_name']))
            $this->form_name = $options['form_name'];

        // Set the HTML5 role.
        $options['role'] = 'form';

        // Set the class for the form type.
        if (!array_key_exists('class', $options)) {
            $options['class'] = $this->getType();
        }

        if (array_key_exists('left_column_class', $options)) {
            $this->setLeftColumnClass($options['left_column_class']);
        }

        if (array_key_exists('left_column_offset_class', $options)) {
            $this->setLeftColumnOffsetClass($options['left_column_offset_class']);
        }

        if (array_key_exists('right_column_class', $options)) {
            $this->setRightColumnClass($options['right_column_class']);
        }

        array_forget($options, [
            'left_column_class',
            'left_column_offset_class',
            'right_column_class'
        ]);

        if (array_key_exists('model', $options)) {
            return $this->model($options);
        }

        if (array_key_exists('error_bag', $options)) {
            $this->setErrorBag($options['error_bag']);
        }

        return $this->form->open($options);
    }

    /**
     * Reset and close the form.
     *
     * @return string
     */
    public function close()
    {
        $this->type = null;

        $this->leftColumnClass = $this->rightColumnClass = null;

        return $this->form->close();
    }

    /**
     * Open a form configured for model binding.
     *
     * @param  array  $options
     * @return string
     */
    protected function model($options)
    {
        $model = $options['model'];

        if (isset($options['url'])) {
            // If we're explicity passed a URL, we'll use that.
            array_forget($options, ['model', 'update', 'store']);
            $options['method'] = isset($options['method']) ? $options['method'] : 'GET';

            return $this->form->model($model, $options);
        }

        // If we're not provided store/update actions then let the form submit to itself.
        if (!isset($options['store']) && !isset($options['update'])) {
            array_forget($options, 'model');
            return $this->form->model($model, $options);
        }

        if (!is_null($options['model']) && $options['model']->exists) {
            // If the form is passed a model, we'll use the update route to update
            // the model using the PUT method.
            $name = is_array($options['update']) ? array_first($options['update']) : $options['update'];
            $route = Str::contains($name, '@') ? 'action' : 'route';

            $options[$route] = array_merge((array) $options['update'], [$options['model']->getRouteKey()]);
            $options['method'] = 'PUT';
        } else {
            // Otherwise, we're storing a brand new model using the POST method.
            $name = is_array($options['store']) ? array_first($options['store']) : $options['store'];
            $route = Str::contains($name, '@') ? 'action' : 'route';

            $options[$route] = $options['store'];
            $options['method'] = 'POST';
        }

        // Forget the routes provided to the input.
        array_forget($options, ['model', 'update', 'store']);

        return $this->form->model($model, $options);
    }

    /**
     * Open a vertical (standard) Bootstrap form.
     *
     * @param  array  $options
     * @return string
     */
    public function vertical(array $options = [])
    {
        $this->setType(Type::VERTICAL);
        $options = array_merge($options, ['class' => "form-vertical"]);
        return $this->open($options);
    }

    /**
     * Open an inline Bootstrap form.
     *
     * @param  array  $options
     * @return string
     */
    public function inline(array $options = [])
    {
        $this->setType(Type::INLINE);
        $options = array_merge($options, ['class' => "form-inline"]);
        return $this->open($options);
    }

    /**
     * Open a horizontal Bootstrap form.
     *
     * @param  array  $options
     * @return string
     */
    public function horizontal(array $options = [])
    {
        $this->setType(Type::HORIZONTAL);
        $options = array_merge($options, ['class' => "form-horizontal"]);
        return $this->open($options);
    }

    /**
     * Create a Bootstrap static field.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function staticField($name, $label = null, $value = null, array $options = [])
    {

        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);

        $options = array_merge(['class' => 'form-control-static'], $options);

        if (is_array($value) and isset($value['html'])) {
            $value = $value['html'];
        } else {
            $value = e($value);
        }

        $label = $this->getLabelTitle($label, $name);
        $inputElement = '<p' . $this->html->attributes($options) . '>' . $value . '</p>';

        $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . $this->getFieldError($name) . $this->getHelpText($name, $options) . '</div>';

        return $this->isAllowed($name) ? $this->getFormGroup($name, $label, $wrapperElement) : "";
    }

    /**
     * Create a Bootstrap text field input.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function text($name, $label = null, $value = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);
        return $this->isAllowed($name) ? $this->input('text', $name, $label, $value, $options) : "";
    }

    /**
     * Create a Bootstrap email field input.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function email($name = 'email', $label = null, $value = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);
        return $this->isAllowed($name) ? $this->input('email', $name, $label, $value, $options) : "";
    }

    /**
     * Create a Bootstrap URL field input.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function url($name, $label = null, $value = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);
        return $this->isAllowed($name) ? $this->input('url', $name, $label, $value, $options) : "";
    }

    /**
     * Create a Bootstrap tel field input.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function tel($name, $label = null, $value = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);

        return $this->isAllowed($name) ? $this->input('tel', $name, $label, $value, $options) : "";
    }

    /**
     * Create a Bootstrap number field input.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function number($name, $label = null, $value = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);

        return $this->isAllowed($name) ? $this->input('number', $name, $label, $value, $options) : "";
    }

    /**
     * Create a Bootstrap date field input.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function date($name, $label = null, $value = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);

        return $this->isAllowed($name) ? $this->input('date', $name, $label, $value, $options) : "";
    }

     /**
     * Create a Bootstrap email time input.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function time($name, $label = null, $value = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);
        return $this->isAllowed($name) ? $this->input('time', $name, $label, $value, $options) : "";
    }

    /**
     * Create a Bootstrap textarea field input.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function textarea($name, $label = null, $value = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);
        return $this->isAllowed($name) ? $this->input('textarea', $name, $label, $value, $options) : "";
    }

    /**
     * Create a Bootstrap password field input.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  array   $options
     * @return string
     */
    public function password($name = 'password', $label = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);
        return $this->isAllowed($name) ? $this->input('password', $name, $label, null, $options) : "";
    }

    /**
     * Create a Bootstrap checkbox input.
     *
     * @param  string   $name
     * @param  string   $label
     * @param  string   $value
     * @param  bool     $checked
     * @param  array    $options
     * @return string
     */
    public function checkbox($name, $label = null, $value = 1, $checked = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);
        $inputElement = $this->checkboxElement($name, $label, $value, $checked, false, $options);

        $wrapperOptions = $this->isHorizontal() ? ['class' => implode(' ', [$this->getLeftColumnOffsetClass(), $this->getRightColumnClass()])] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . $this->getFieldError($name) . $this->getHelpText($name, $options) . '</div>';

        return $this->isAllowed($name) ? $this->getFormGroup($name, null, $wrapperElement) : "";
    }

    /**
     * Create a single Bootstrap checkbox element.
     *
     * @param  string   $name
     * @param  string   $label
     * @param  string   $value
     * @param  bool     $checked
     * @param  bool     $inline
     * @param  array    $options
     * @return string
     */
    public function checkboxElement($name, $label = null, $value = 1, $checked = null, $inline = false, array $options = [])
    {
        $label = $label === false ? null : $this->getLabelTitle($label, $name);

        $labelOptions = $inline ? ['class' => 'checkbox-inline'] : [];
        $inputElement = $this->form->checkbox($name, $value, $checked, $options);
        $labelElement = '<label ' . $this->html->attributes($labelOptions) . '>' . $inputElement . '<span class="label-text">'. $label . '</span></label>';

        return $inline ? $labelElement : '<div class="checkbox animated-checkbox">' . $labelElement . '</div>';
    }

    /**
     * Create a collection of Bootstrap checkboxes.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  array   $choices
     * @param  array   $checkedValues
     * @param  bool    $inline
     * @param  array   $options
     * @return string
     */
    public function checkboxes($name, $label = null, $choices = [], $checkedValues = [], $inline = false, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);

        $elements = '';

        foreach ($choices as $value => $choiceLabel) {
            $checked = in_array($value, (array) $checkedValues);

            $elements .= $this->checkboxElement($name, $choiceLabel, $value, $checked, $inline, $options);
        }

        $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $elements . $this->getFieldError($name) . $this->getHelpText($name, $options) . '</div>';

        return $this->isAllowed($name) ? $this->getFormGroup($name, $label, $wrapperElement) : "";
    }

    /**
     * Create a Bootstrap radio input.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  string  $value
     * @param  bool    $checked
     * @param  array   $options
     * @return string
     */
    public function radio($name, $label = null, $value = null, $checked = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);

        $inputElement = $this->radioElement($name, $label, $value, $checked, false, $options);

        $wrapperOptions = $this->isHorizontal() ? ['class' => implode(' ', [$this->getLeftColumnOffsetClass(), $this->getRightColumnClass()])] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . '</div>';

        return $this->isAllowed($name) ? $this->getFormGroup(null, $label, $wrapperElement) : "";
    }

    /**
     * Create a single Bootstrap radio input.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  string  $value
     * @param  bool    $checked
     * @param  bool    $inline
     * @param  array   $options
     * @return string
     */
    public function radioElement($name, $label = null, $value = null, $checked = null, $inline = false, array $options = [])
    {
        $label = $label === false ? null : $this->getLabelTitle($label, $name);

        $value = is_null($value) ? $label : $value;

        $labelOptions = $inline ? ['class' => 'radio-inline'] : [];

        $inputElement = $this->form->radio($name, $value, $checked, $options);
        $labelElement = '<label ' . $this->html->attributes($labelOptions) . '>' . $inputElement .'<span class="label-text">'. $label .'</span> </label>';

        return $inline ? $labelElement : '<div class="radio">' . $labelElement . '</div>';
    }

    /**
     * Create a collection of Bootstrap radio inputs.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  array   $choices
     * @param  string  $checkedValue
     * @param  bool    $inline
     * @param  array   $options
     * @return string
     */
    public function radios($name, $label = null, $choices = [], $checkedValue = null, $inline = false, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);
        $elements = '';

        foreach ($choices as $value => $choiceLabel) {
            $checked = $value === $checkedValue;

            $elements .= $this->radioElement($name, $choiceLabel, $value, $checked, $inline, $options);
        }

        $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
        $wrapperElement = '<div class="animated-radio-button"' . $this->html->attributes($wrapperOptions) . '>' . $elements . $this->getFieldError($name) . $this->getHelpText($name, $options) . '</div>';

        return $this->isAllowed($name) ? $this->getFormGroup($name, $label, $wrapperElement) : "";
    }

    /**
     * Create a Bootstrap label.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function label($name, $value = null, array $options = [])
    {
        $options = $this->getLabelOptions($options);

        $escapeHtml = false;

        if (is_array($value) and isset($value['html'])) {
            $value = $value['html'];
        } elseif ($value instanceof HtmlString) {
            $value = $value->toHtml();
        } else {
            $escapeHtml = true;
        }

        return $this->form->label($name, $value, $options, $escapeHtml);
    }

    /**
     * Create a Boostrap submit button.
     *
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function submit($value = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);

        $options = array_merge(['class' => 'btn btn-primary'], $options);

        $inputElement = $this->form->submit($value, $options);

        $wrapperOptions = $this->isHorizontal() ? ['class' => implode(' ', [$this->getLeftColumnOffsetClass(), $this->getRightColumnClass()])] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>'. $inputElement . '</div>';

        return $this->isAllowed($name) ? $this->getFormGroup(null, null, $wrapperElement) : "";
    }

    /**
     * Create a Boostrap button.
     *
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function button($value = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);

        $options = array_merge(['class' => 'btn btn-primary'], $options);

        $inputElement = $this->form->button($value, $options);

        $wrapperOptions = $this->isHorizontal() ? ['class' => implode(' ', [$this->getLeftColumnOffsetClass(), $this->getRightColumnClass()])] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>'. $inputElement . '</div>';

        return $this->isAllowed($name) ? $this->getFormGroup(null, null, $wrapperElement) : "";
    }

    /**
     * Create a Boostrap file upload button.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  array   $options
     * @return string
     */
    public function file($name, $label = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);

        $label = $this->getLabelTitle($label, $name);

        $options = array_merge(['class' => 'filestyle', 'data-buttonBefore' => 'true'], $options);

        $options = $this->getFieldOptions($options, $name);
        $inputElement = $this->form->input('file', $name, null, $options);

        $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . $this->getFieldError($name) . $this->getHelpText($name, $options) . '</div>';

        return $this->isAllowed($name) ? $this->getFormGroup($name, $label, $wrapperElement) : "";
    }

    /**
     * Create the input group for an element with the correct classes for errors.
     *
     * @param  string  $type
     * @param  string  $name
     * @param  string  $label
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function input($type, $name, $label = null, $value = null, array $options = [])
    {
        $label = $this->getLabelTitle($label, $name);

        $optionsField = $this->getFieldOptions(array_except($options, ['suffix', 'prefix']), $name);

        $inputElement = '';

         if(isset($options['prefix'])) {
            $inputElement = $options['prefix'];
        }

        $inputElement .= $type === 'password' ? $this->form->password($name, $optionsField) : $this->form->{$type}($name, $value, $optionsField);

         if(isset($options['suffix'])) {
            $inputElement .= $options['suffix'];
        }

         if(isset($options['prefix']) || isset($options['suffix'])) {
            $inputElement = '<div class="input-group">' . $inputElement . '</div>';
        }

        $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . $this->getFieldError($name) . $this->getHelpText($name, $optionsField) . '</div>';

        return $this->getFormGroup($name, $label, $wrapperElement);
    }

    /**
     * Create an addon button element.
     *
     * @param  string  $label
     * @param  array  $options
     * @return string
     */
    public function addonButton($label, $options = [])
    {
        $attributes = array_merge(['class' => 'btn', 'type' => 'button'], $options);

        if (isset($options['class'])) {
            $attributes['class'] .= ' btn';
        }

        return '<div class="input-group-btn"><button ' . $this->html->attributes($attributes) . '>'.$label.'</button></div>';
    }

    /**
     * Create an addon text element.
     *
     * @param  string  $text
     * @param  array  $options
     * @return string
     */
    public function addonText($text, $options = [])
    {
        return '<div class="input-group-addon"><span ' . $this->html->attributes($options) . '>'.$text.'</span></div>';
    }

    /**
     * Create an addon icon element.
     *
     * @param  string  $icon
     * @param  array  $options
     * @return string
     */
    public function addonIcon($icon, $options = [])
    {
        $prefix = array_get($options, 'prefix', $this->getIconPrefix());

        return '<div class="input-group-addon"><span ' . $this->html->attributes($options) . '><i class="'.$prefix.$icon.'"></i></span></div>';
    }

    /**
     * Create a hidden field.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function hidden($name, $value = null, $options = [])
    {
        return $this->form->hidden($name, $value, $options);
    }

    /**
     * Create a select box field.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  array   $list
     * @param  string  $selected
     * @param  array   $options
     * @return string
     */
    public function select($name, $label = null, $list = [], $selected = null, array $options = [])
    {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);
        $label = $this->getLabelTitle($label, $name);

        $options = $this->getFieldOptions($options, $name);
        $inputElement = $this->form->select($name, $list, $selected, $options);

        $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . $this->getFieldError($name) . $this->getHelpText($name, $options) . '</div>';

        return $this->isAllowed($name) ? $this->getFormGroup($name, $label, $wrapperElement) : "";
    }

    /**
     * Wrap the content in Laravel's HTML string class.
     *
     * @param  string  $html
     * @return \Illuminate\Support\HtmlString
     */
    protected function toHtmlString($html)
    {
        return new HtmlString($html);
    }

    /**
     * Get the label title for a form field, first by using the provided one
     * or titleizing the field name.
     *
     * @param  string  $label
     * @param  string  $name
     * @return mixed
     */
    protected function getLabelTitle($label, $name)
    {
        if ($label === false) {
            return null;
        }

        if (is_null($label) && Lang::has("forms.{$name}")) {
            return Lang::get("forms.{$name}");
        }

        return $label ?: str_replace('_', ' ', Str::title($name));
    }

    /**
     * Get a form group comprised of a form element and errors.
     *
     * @param  string  $name
     * @param  string  $element
     * @return \Illuminate\Support\HtmlString
     */
    protected function getFormGroupWithoutLabel($name, $element)
    {
        $options = $this->getFormGroupOptions($name);

        return $this->toHtmlString('<div' . $this->html->attributes($options) . '>' . $element . '</div>');
    }

    /**
     * Get a form group comprised of a label, form element and errors.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  string  $element
     * @return \Illuminate\Support\HtmlString
     */
    protected function getFormGroupWithLabel($name, $value, $element)
    {
        $options = $this->getFormGroupOptions($name);

        return $this->toHtmlString('<div' . $this->html->attributes($options) . '>' . $this->label($name, $value) . $element . '</div>');
    }

    /**
     * Get a form group with or without a label.
     *
     * @param  string  $name
     * @param  string  $label
     * @param  string  $element
     * @return string
     */
    public function getFormGroup($name = null, $label = null, $wrapperElement)
    {
        if (is_null($label)) {
            return $this->getFormGroupWithoutLabel($name, $wrapperElement);
        }
        return $this->getFormGroupWithLabel($name, $label, $wrapperElement);
    }

    /**
     * Merge the options provided for a form group with the default options
     * required for Bootstrap styling.
     *
     * @param  string $name
     * @param  array  $options
     * @return array
     */
    protected function getFormGroupOptions($name = null, array $options = [])
    {
        $class = 'form-group';

        if ($name) {
            $class .= ' ' . $this->getFieldErrorClass($name);
        }

        return array_merge(['class' => $class], $options);
    }

    /**
     * Merge the options provided for a field with the default options
     * required for Bootstrap styling.
     *
     * @param  array  $options
     * @param  string $name
     * @return array
     */
    protected function getFieldOptions(array $options = [], $name = null)
    {
        $options['class'] = trim('form-control ' . $this->getFieldOptionsClass($options));

        // If we've been provided the input name and the ID has not been set in the options,
        // we'll use the name as the ID to hook it up with the label.
        if ($name && ! array_key_exists('id', $options)) {
            $options['id'] = $name;
        }

        return $options;
    }

    /**
     * Returns the class property from the options, or the empty string
     *
     * @param   array  $options
     * @return  string
     */
    protected function getFieldOptionsClass(array $options = [])
    {
        return array_get($options, 'class');
    }

    /**
     * Merge the options provided for a label with the default options
     * required for Bootstrap styling.
     *
     * @param  array  $options
     * @return array
     */
    protected function getLabelOptions(array $options = [])
    {
        $class = 'control-label';
        if ($this->isHorizontal()) {
            $class .= ' ' . $this->getLeftColumnClass();
        }

        return array_merge(['class' => trim($class)], $options);
    }

    /**
     * Get the form type.
     *
     * @return string
     */
    public function getType()
    {
        return isset($this->type) ? $this->type : $this->config->get('bootstrap_form.type');
    }

    /**
     * Determine if the form is of a horizontal type.
     *
     * @return bool
     */
    public function isHorizontal()
    {
        return $this->getType() === Type::HORIZONTAL;
    }

    /**
     * Set the form type.
     *
     * @param  string  $type
     * @return void
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get the column class for the left column of a horizontal form.
     *
     * @return string
     */
    public function getLeftColumnClass()
    {
        return $this->leftColumnClass ?: $this->config->get('bootstrap_form.left_column_class');
    }

    /**
     * Set the column class for the left column of a horizontal form.
     *
     * @param  string  $class
     * @return void
     */
    public function setLeftColumnClass($class)
    {
        $this->leftColumnClass = $class;
    }

    /**
     * Get the column class for the left column offset of a horizontal form.
     *
     * @return string
     */
    public function getLeftColumnOffsetClass()
    {
        return $this->leftColumnOffsetClass ?: $this->config->get('bootstrap_form.left_column_offset_class');
    }

    /**
     * Set the column class for the left column offset of a horizontal form.
     *
     * @param  string  $class
     * @return void
     */
    public function setLeftColumnOffsetClass($class)
    {
        $this->leftColumnOffsetClass = $class;
    }

    /**
     * Get the column class for the right column of a horizontal form.
     *
     * @return string
     */
    public function getRightColumnClass()
    {
        return $this->rightColumnClass ?: $this->config->get('bootstrap_form.right_column_class');
    }

    /**
     * Set the column class for the right column of a horizontal form.
     *
     * @param  string  $class
     * @return void
     */
    public function setRightColumnClass($class)
    {
        $this->rightColumnClass = $class;
    }

    /**
     * Get the icon prefix.
     *
     * @return string
     */
    public function getIconPrefix()
    {
        return $this->iconPrefix ?: $this->config->get('bootstrap_form.icon_prefix');
    }

     /**
     * Get the error class.
     *
     * @return string
     */
    public function getErrorClass()
    {
        return $this->errorClass ?: $this->config->get('bootstrap_form.error_class');
    }

    /**
     * Get the error bag.
     *
     * @return string
     */
    protected function getErrorBag()
    {
        return $this->errorBag ?: $this->config->get('bootstrap_form.error_bag');
    }

    /**
     * Set the error bag.
     *
     * @param  $errorBag  string
     * @return void
     */
    protected function setErrorBag($errorBag)
    {
        $this->errorBag = $errorBag;
    }

    /**
     * Flatten arrayed field names to work with the validator, including removing "[]",
     * and converting nested arrays like "foo[bar][baz]" to "foo.bar.baz".
     *
     * @param  string  $field
     * @return string
     */
    public function flattenFieldName($field)
    {
        return preg_replace_callback("/\[(.*)\\]/U", function ($matches) {
            if (!empty($matches[1]) || $matches[1] === '0') {
                return "." . $matches[1];
            }
        }, $field);
    }

    /**
     * Get the MessageBag of errors that is populated by the
     * validator.
     *
     * @return \Illuminate\Support\MessageBag
     */
    protected function getErrors()
    {
        return $this->form->getSessionStore()->get('errors');
    }

    /**
     * Get the first error for a given field, using the provided
     * format, defaulting to the normal Bootstrap 3 format.
     *
     * @param  string  $field
     * @param  string  $format
     * @return mixed
     */
    protected function getFieldError($field, $format = '<span class="help-block">:message</span>')
    {
        $field = $this->flattenFieldName($field);

        if ($this->getErrors()) {
            $allErrors = $this->config->get('bootstrap_form.show_all_errors');

            if ($this->getErrorBag()) {
                $errorBag = $this->getErrors()->{$this->getErrorBag()};
            } else {
                $errorBag = $this->getErrors();
            }

            if ($allErrors) {
                return implode('', $errorBag->get($field, $format));
            }

            return $errorBag->first($field, $format);
        }
    }

    /**
     * Return the error class if the given field has associated
     * errors, defaulting to the normal Bootstrap 3 error class.
     *
     * @param  string  $field
     * @param  string  $class
     * @return string
     */
    protected function getFieldErrorClass($field)
    {
        return $this->getFieldError($field) ? $this->getErrorClass() : null;
    }

    /**
     * Get the help text for the given field.
     *
     * @param  string  $field
     * @param  array   $options
     * @return \Illuminate\Support\HtmlString
     */
    protected function getHelpText($field, array $options = [])
    {
        if (array_key_exists('help_text', $options)) {
            return $this->toHtmlString('<span class="help-block">' . e($options['help_text']) . '</span>');
        }

        return '';
    }


    // New form elements for Kubernesis

    // 1 item selection
    public function selectize($name, $label = null, $list = [], $selected = null, array $options = []) {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);
        $options = $this->getFieldOptions($options, $name);
        if($this->form->getModel() != NULL)
            $selected = $this->form->getModel()->{$name};
        $inputElement = $this->form->select($name, $list, $selected, $options);
        $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . $this->getFieldError($name) . $this->getHelpText($name, $options) . '</div>';
        $wrapperElement .= '
        <script>
            $(function() {
                $("#'.$name.'").selectize({
                    maxItems: 1
                });
            })
        </script>
        ';
        return $this->isAllowed($name) ? $this->getFormGroup($name, $label, $wrapperElement) : "";
    }

    // can have multiple selection
    public function selectizeMany($name, $label = null, $list = [], $selected = [], array $options = []) {
        $jsname = str_replace("-","",$name);
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);
        $options = $this->getFieldOptions($options, $name."[]");
        $inputElement = $this->form->select($name."[]", $list, $selected, $options);
        $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . $this->getFieldError($name."[]") . $this->getHelpText($name."[]", $options) . '</div>';
        $wrapperElement .= '
            <script type="text/javascript">
            $(function() {
                var '.$jsname.' = $("#'.$name.'").selectize({
                    allowEmptyOption: true,
                    persist: false,
                    maxItems: null
                });
                '.$jsname.'[0].selectize.clear();
                '.$jsname.'[0].selectize.setValue(["'.implode('", "',$selected).'"]);
            })
            </script>
        ';
        return $this->isAllowed($name) ? $this->getFormGroup($name, $label, $wrapperElement) : "";
    }

    // list is from route
    public function selectizeAjax($name, $label = null, $route, $selected = [], array $options = []) {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);
        $options = $this->getFieldOptions($options, $name);
        if($this->form->getModel() != NULL)
            $selected = $this->form->getModel()->{$name};
        $inputElement = $this->form->select($name."[]", [], $selected, $options);
        $wrapperOptions = $this->isHorizontal() ? ['class' => $this->getRightColumnClass()] : [];
        $wrapperElement = '<div' . $this->html->attributes($wrapperOptions) . '>' . $inputElement . $this->getFieldError($name) . $this->getHelpText($name, $options) . '</div>';
        $wrapperElement .= '
            <script>
            $(function() {
                $("#'.$name.'").selectize({
                    valueField: "id",
                    labelField: "name",
                    searchField: "name",
                    options: [],
                    persist: false,
                    loadThrottle: 600,
                    create: false,
                    allowEmptyOption: false,
                    maxItems: 1,
                    render: {
                        item: function(item, escape) {
                            return "<li>" +
                                (item.name ? "<span>" + escape(item.name) + "</span>" : "") +
                            "</li>";
                        },
                        option: function(item, escape) {
                            var label = item.name;
                            return "<li style=\"display:block; padding-left:10px;\">" + escape(label) + "</li>";
                        }
                    },
                    load: function(query, callback) {
                        if (!query.length) return callback();
                        $.ajax({
                            url: "'. $route .'/"+encodeURIComponent(query),
                            type: "GET",
                            dataType: "json",
                            error: function() {
                                callback();
                            },
                            success: function(res) {
                                callback(res);
                            }
                        });
                    },
                    onInitializeValue: function () {
                        $(this)[0].onSearchChange("'.($selected[0]??null).'");
                    }
                });
            })
            </script>
        ';
        return $this->isAllowed($name) ? $this->getFormGroup($name, $label, $wrapperElement) : "";
    }

    public function toggle($name, $label = null, $value = null, $options = [], $setting = [ 'off' => 'Off', 'on' => 'On']) {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);
        $optionsField = $this->getFieldOptions(array_except($options, ['suffix', 'prefix']), $name);
        $label = $this->getLabelTitle($label, $name);
        $valueON =  '$("#'.$name.'").prop("checked")';
        $valueOFF = '$("#'.$name.'").removeProp("checked")';
        $disabled = '$("#'.$name.'").attr("disabled", "disabled")';
        $wrapperElement = '<br/><input '.$this->html->attributes($optionsField).' name="'.$name.'" type="checkbox" >
        <script>
        $(function() {
            $("#'.$name.'").bootstrapToggle({
                on: "'.$setting['on'].'",
                off: "'.$setting['off'].'"
            });
            $("#'.$name.'").bootstrapToggle("'.strtolower($value) .'");
            '. (strtolower($value) == 'on' ? $valueON : $valueOFF ) .'
            '.(isset($setting['disabled']) && $setting['disabled'] == 'disabled' ?$disabled : '') .'
        })
        </script>';
        return $this->isAllowed($name) ? $this->getFormGroup($name, $label, $wrapperElement) : "";
    }

    public function toggleFlip($name, $label = null, $value = null, $options = [], $setting = [ 'off' => 'Off', 'on' => 'On']) {
        unset($options['id']);
        $options = array_merge($options, ['id' => $name]);
        $optionsField = $this->getFieldOptions(array_except($options, ['suffix', 'prefix']), $name);
        $label = $this->getLabelTitle($label, $name);
        $valueON =  '$("#'.$name.'").prop("checked")';
        $valueOFF = '$("#'.$name.'").removeProp("checked")';
        $disabled = '$("#'.$name.'").attr("disabled", "disabled")';
        $wrapperElement = '<br/><input '.$this->html->attributes($optionsField).' name="'.$name.'" type="checkbox" >
        <script>
        $(function() {
            $("#'.$name.'").bootstrapToggle({
                on: "'.$setting['on'].'",
                off: "'.$setting['off'].'"
            });
            $("#'.$name.'").bootstrapToggle("'.strtolower($value) .'");
            '. (strtolower($value) == 'on' ? $valueON : $valueOFF ) .'
            '.(isset($setting['disabled']) && $setting['disabled'] == 'disabled' ?$disabled : '') .'
        })
        </script>';
        return $this->isAllowed($name) ?  $this->getFormGroup($name, $label, $wrapperElement) : "";
    }


    /**
     * Select like dropdown using selectize
     * @param  string $name       Name of element
     * @param  array  $list       selection list, null if ajax
     * @param  string $selected   initial value
     * @param  array  $options    options
     * @return string             HTML code to display the selectize data
     */
    function sselectize ($name, $list = [], $selected = null, $options = []) {
        $selectize_loaded = $_SERVER['sselectize']['selectize_loaded']??false;

        $table = null;
        $field = null;
        if (isset($options['from']))    // from: $table.$field short syntax
        {
            $from_exploded = explode('.', $options['from']);
            if (count($from_exploded) >= 2)
            {
                $from_exploded = array_reverse($from_exploded);
                $table = $from_exploded[1];
                $field = $from_exploded[0];
            }
            else
                $table = $from;
        }

        $type = isset($options['type']) ? $options['type'] : 'select';
        $table = isset($options['table']) ? $options['table'] : ($table ? $table : null);
        $field = isset($options['field']) ? $options['field'] : ($field ? $field : 'name');
        $create = isset($options['create']) && ($options['create'] !== false && $options['create'] !== 'false') ? 'true' : 'false';
        $multiple = isset($options['multiple']) && ($options['multiple'] !== false && $options['multiple'] !== 'false') ? (ctype_digit($options['multiple'])?$options['multiple']:'null') : '1';
        $js_options = isset($options['js_options']) && count($options['js_options']??[]) > 0 ? $options['js_options'] : null;
        $js_attach = isset($options['js_attach']) ? $options['js_attach'] : null;
        $except = isset($options['except']) ? $options['except'] : [];
        $input_width = isset($options['input_width']) ? $options['input_width'] : '30';
        $js_render = $options['js_render'] ?? null;
        $all_data = isset($options['all_data']) && $options['all_data'] == true ? true : false;

        $model = isset($options['model']) ? $options['model'] : null;
        $url = isset($options['url']) ? $options['url'] : null;
        $textarea_full = isset($options['textarea_full']) ? $options['textarea_full'] : false;
        $attributes = $options['attributes']??[];
        if ($multiple != 1)
            $attributes['multiple'] = 'required';
        $placeholder = isset($attributes['placeholder']) ? $attributes['placeholder'] : null;
        $preload = isset($options['preload']) && ($options['preload'] === false || $options['preload'] == 'false') ? 'false' : (!isset($options['preload']) ? 'false' : 'true' );
        $limit = isset($options['limit']) ? $options['limit'] : 10;
        $order_field = isset($options['order_field']) ? $options['order_field'] : $field;
        $order_by = isset($options['order_by']) ? $options['order_by'] : 'asc';

        // use plural(name)_id to get table when it's not set
        if (!$table && preg_match('/_id$/', $name) && class_exists('Schema'))
        {
            $temp_table = str_plural(preg_replace('/_id[\[\]]*/', '', $name));
            if (Schema::hasTable($temp_table))
                $table = $temp_table;
            else if (Schema::hasTable(str_plural($temp_table)))
                $table = str_plural($temp_table);
        }

        // guess table from name
        else if (!$table)
        {
            // guess model based on current controller to get start of table name
            $this_model = null;
            if ($this->model)
            {
                $this_model = $this->model;
            }
            else if (class_exists('Request'))
            {
                $controller_action = Request::route()->getAction()['controller'];
                $name_stripped = preg_replace('/Controller@[a-z]*$/', '', $controller_action);
                $name_stripped = preg_replace('/^.*Controllers\\\\/', '', $name_stripped);
                $try_model = 'App\\Models\\'.$name_stripped;
                if (class_exists($try_model))
                    $model_name = $try_model;
                else
                {
                    $try_model = 'App\\Models\\'.substr($name_stripped, strrpos($name_stripped, '\\') + 1);
                    if (class_exists($try_model))
                        $model_name = $try_model;
                }
                if (isset($model_name))
                    $this_model = new $model_name();
            }

            if ($this_model)
            {
                // guess table from dot syntax name
                $from = $name;
                $last_table = $this_model->getTable();
                foreach(explode('.', $from) as $table)
                {
                    if (Schema::hasTable($table))
                        $last_table = $table;
                    else if (Schema::hasTable(str_plural($table)))
                        $last_table = str_plural($table);
                    else
                    {
                        if ($field == null)
                            $field = $table;
                        break;
                    }
                }
                $table = $last_table;
            }
        }

        // get route from tablename
        if (!$url && class_exists('Route'))
        {
            $url = '';
            $regex_route = '/'.$table.'\\/list$/';
            $regex_route_camel_case = '/'.camel_case($table).'\\/list$/';
            $regex_route_dashed = '/'.str_replace('_', '-', $table).'\\/list$/';
            $route_collection = Route::getRoutes();
            foreach ($route_collection as $route)
            {
                if (preg_match($regex_route, $route->uri())
                    || preg_match($regex_route_camel_case, $route->uri())
                    || preg_match($regex_route_dashed, $route->uri()))
                {
                    $url = '/'.$route->uri();
                    $url = config('app.url').$url;
                    break;
                }
            }
        }

        // add js options
        $add_js = '';
        if ($js_options)
            foreach($js_options as $json_field => $code)
                $add_js .= ",\n".$json_field. ': '.$code;

        // it has <options>, use id (see below)
        $has_options = is_array($list) && count($list) > 0;

        // if _id or has_options or is multiple then use
        $key = isset($options['key']) ? $options['key'] : (preg_match('/_id[\[\]]*/', $name) || $has_options || $multiple != 1 || !$create ? 'id' : $field);

        // set display
        // Note: when it has url it's using ajax so get the field value,
        // else it's using <options> so get the "value" attribute
        if ($multiple != '1' || $url)
            $display = isset($options['display']) ? $options['display'] : $field;
        else
            $display = isset($options['display']) ? $options['display'] : 'value';

        // set default options
        $options = $list;
        if ($selected)
            $selected_value = $selected;
        else
            $selected_value = $this->form->getValueAttribute($name, null);

        $selected_text = null;

        if ($selected_value && $table)
        {
            if ($table == 'users')
                $model = new \App\User;

            // guess model from table name
            if ($model !== null)
            {
                $model = new $model();
            }
            else
            {
                $from_model = null;
                $tmp_model_start = '\\App\\Models\\';
                $tmp_model_class = studly_case(str_singular($table ?? ''));
                if (class_exists($tmp_model_start.$tmp_model_class))
                {
                    $tmp_model = $tmp_model_start.$tmp_model_class;
                    $from_model = $tmp_model;
                }
                else
                {
                    // try to find inside folders
                    $regex_route = '/'.$table.'$/';
                    $route_collection = Route::getRoutes();
                    foreach ($route_collection as $route)
                    {
                        if (preg_match($regex_route, $route->uri()))
                        {
                            $guess_model_path = preg_replace("/(.*[\..]*)(".$table."[\\..]*.*)/", "$1", $route->getName());
                            $guess_model = $tmp_model_start.str_replace('.', '\\', studly_case($guess_model_path)).$tmp_model_class;
                            if (class_exists($guess_model))
                                $from_model = $guess_model;
                            break;
                        }
                    }
                }

                // can't find eloquent model, just use builder
                // and accessors and mutators won't work
                if ($from_model)
                {
                    $model = new $from_model();
                }
                else
                {
                    $model = DB::table($table);
                }
            }

            if (ctype_digit(trim($selected_value)))
            {

                // README: if the error is can't find accessor column,
                //         it probably is because it went to DB::table above,
                //         where accessors won't work.
                //         So just specify the correct model and it'll work.
                $tmp_selected_text = $model->get()->where($key, (int) $selected_value)->first();

                if ($tmp_selected_text)
                {
                    $selected_text = $tmp_selected_text->{$field};
                }
                else
                {
                    $selected_text = $selected_value;
                }
            }
            else
            {
                $selected_text = $model->get()->where($field, $selected_value)->first();
                if ($selected_text)
                {
                    $selected_value = $selected_text->id;
                    $selected_text = $selected_text->{$field};
                }
                else
                    $selected_text = $selected_value;
            }
            $options = [$selected_value => $selected_text];
        }
        else if (trim($this->form->getValueAttribute($name)) != '')
        {
            $selected_value = $this->form->getValueAttribute($name);
        }

        // set default option when not using select
        $id_set = true;
        if (!isset($attributes['id']))
        {
            $tmp_name = preg_replace('/[\[\]]/', '_', $name);
            $attributes['id'] = str_replace('.', '_', $tmp_name);
            $id_set = false;
        }
        $slashed_id = str_replace('.', '\\\\.', $attributes['id']);

        $element_name = $name;
        $element_value = $selected_value;
        $has_separate_hidden = false;
        $output = '';
        // if id was not set then use a different name for widget/control
        if (!$id_set && $type != 'select' && ($key != $field || strpos($name, '.') !== false))
        {
            $tmp_name = preg_replace('/[\[\]]/', '_', $name);
            $different_id = str_replace('.', '-', $tmp_name).'_selectize';
            // create hidden element with original name
            if ($type === 'textarea')
                $output .= Form::textarea($name, $selected_value, ['id' => $different_id, 'style' => 'display:none']);
            else
                $output .= Form::hidden($name, $selected_value, ['id' => $different_id]);

            //  and use a different name for the widget/control
            $element_name = $different_id;
            if ($selected_value)
                $element_value = $options[$selected_value]??null;
            $has_separate_hidden = true;
        }
        else
        {
            $tmp_name = preg_replace('/[\[\]]/', '_', $element_name);
            $attributes['id'] = $tmp_name;
            $different_id = str_replace('.', '-', $tmp_name);
        }
        switch ($type)
        {
            case 'select':
                $output .= $this->form->select($element_name, $options, $selected_value, $attributes);
            break;
            case 'text':
                $output .= $this->form->text($element_name, $element_value, $attributes);
            break;
            case 'password':
                $output .= $this->form->input('password', $element_name, $element_value, $attributes);
            break;
            case 'textarea':
                $output .= $this->form->textarea($element_name, $element_value, $attributes);
            break;
        }
        if ($type !== 'textarea')
        {
            $output .= (!$selectize_loaded ? '<script src="'.asset('/bower_components/selectize/dist/js/standalone/selectize.js').'"></script>' : '');
            $outputs = '';
            $output .= '<script>
                '.(!$has_options?'
                if ("'. $url .'" == "")
                    console.log("Warning: URL is empty. Maybe you don\'t have '. $table .'/list route?");
                ':'').'
                var '.$slashed_id.'_data = [];
                var select = $("#'.$slashed_id.'").selectize({
                    persist: true,
                    valueField: "'.$key.'",
                    labelField: "'.$display.'",
                    searchField: "'.$field.'",
                    create: '.$create.',
                    maxItems: '.$multiple.','.
                    ($placeholder? '
                    placeholder: "'.$placeholder."\",\n" : '').'
                    createOnBlur: true,
                    openOnFocus: true,
                    preload: '.$preload.',
                    render: {
                        option: function(item, escape) {
                            '.$slashed_id.'_data.push(item);'.
                            ($js_render ?
                                'return '.$js_render :
                                'return "<div>"+escape(item.'.$field.')+"</div>"'
                            ).';
                        }
                    },
                    load: function (query, callback) {
                        if ("'.$url.'" == "")
                            return true;
                        var self = this;
                        var search="'.$field.'";
                        if (!$("#'.$slashed_id.'").data("inited_already"))
                            search="'.$key.'";
                        $.when( $.ajax({
                            url: "'.$url.'?search="+search+"&field='.$field.($add_js!=''||$all_data?'&all_data=1':'').'",
                            type: "GET",
                            data: {
                                except: ['.join(",", $except).'],
                                "query": query,
                                page_limit: 10
                            },
                            error: function() {
                                callback();
                            },
                            success: function(res) {
                                res = JSON.parse(res);
                                callback(res.results);
                            },
                        }) ).then (function () {
                            if (!$("#'.$slashed_id.'").data("inited_already"))
                            {
                                $("#'.$slashed_id.'").data("inited_already", true);
                                self.setValue(["'.(is_array($selected_value)?join('", "',$selected_value):$selected_value).'"], true);
                            }
                        });
                    },
                    onInitialize: function () {
                        $(this)[0].onSearchChange("'.(is_array($selected_value)?join('", "',$selected_value):$selected_value).'");
                    },
                    onItemAdd: function (event, item) {
                        text = $(item).text();
                        all_data = '.$slashed_id.'_data;
                        data = null;
                        for(i=0; i < all_data.length; ++i)
                        {
                            if (all_data[i].'.$display.' == text)
                                data = all_data[i];
                        }
                        if (data)
                            $("#'.$slashed_id.'").trigger("item_add", data);
                    }
                    '.
                    $add_js.
                '})
                '.$js_attach;
                if ($has_separate_hidden)
                {
                    $output .= '.on("change", function (value) {
                            $("#'.$different_id.'").val($("#'.$slashed_id.'").val());
                        });'."\n";
                }
                else
                {
                    $output .= ';'."\n";
                }
                $output .= (!$selectize_loaded ? 'head.load("'.asset('/bower_components/selectize/dist/css/selectize.bootstrap3.css').'");' : '')."\n";
                $output .= (!$selectize_loaded ? 'head.load("'.asset('/css/selectize.css').'");' : '')."\n";
                $tabs = '$(function () {
                        $(":input").filter(function () {
                            if (this.id != "")
                                return this.id.match(/selectized$/);
                            return false;
                        }).on("keydown", function (e) {
                            var keyCode = e.keyCode || e.which;

                            if (keyCode == 9) {
                                e.preventDefault();
                                var inputs = $("div.selectize-control.form-control,:input:not(.selectized):not([type=hidden])").filter(":visible").filter(function () {
                                    if (this.id != "")
                                        return !this.id.match(/selectized$/);
                                    if (this.class != "")
                                        return !this.id.match(/ui-datepicker/);
                                    return true;
                                });
                                if (!e.shiftKey)
                                    add_to_index = 1;
                                else
                                    add_to_index = -1;

                                var tab_to = null;
                                for (i=0; i<inputs.length; ++i)
                                {
                                    if ($(e.target).parents(".form-control")[0] == inputs[i])
                                    {
                                        tab_to_index = i + add_to_index;
                                        if (tab_to_index >= inputs.length)
                                            tab_to = inputs[0];
                                        else if(tab_to_index < 0)
                                            tab_to = inputs[inputs.length - 1];
                                        else
                                            tab_to = inputs[tab_to_index];
                                        break;
                                    }
                                }
                                if ($(tab_to).is(":input"))
                                    $(tab_to).focus();
                                else
                                    $(tab_to).find(":input").focus();
                            }
                        });
                    });
                ';
                $output .= (!$selectize_loaded)?$tabs:'';
            $output .= '</script>';
            $_SERVER['sselectize']['selectize_loaded'] = true;
        }
        else
        {
            if ($textarea_full)
            {
                $jquery_autocomplete_loaded = Request::get('jquery_autocomplete_loaded', false);
                $output .= (!$jquery_autocomplete_loaded ? '<script src="'.asset('/bower_components/jquery-auto-complete/jquery.auto-complete.js').'"></script>' : '');
                $output .= '<script>
                    if ("'. $url .'" == "")
                        console.log("Warning: URL is empty. Maybe you don\'t have '. $table .'/list route?");
                    var xhr_'.$attributes['id'].';
                    $("#'.$slashed_id.'").autoComplete({
                        minChars: 0,
                        cache: false,'
                        ($placeholder? ' placeholder: "'.$placeholder."\",\n" : '').
                        'source: function (term, response) {
                            try { xhr_'.$attributes['id'].'.abort(); } catch (e) {};
                            xhr_'.$attributes['id'].' = $.getJSON(
                                "'.$url.'?field='.$field.($add_js!=''?'&all_data=1':'').'",
                                { query: term ,
                                    limit: '.$limit.'
                                },
                                function (data) {
                                    results = data.results;
                                    response(results);
                                }
                            );
                        },
                        renderItem: function (item, search){
                            search = search.replace(/[-\/\\^$*+?.()|[\]{}]/g, \'\\$&\');
                            var re = new RegExp("(" + search.split(\' \').join(\'|\') + ")", "gi");
                            return "<div class=\"autocomplete-suggestion\" data-key=\""+item.'.$key.'+"\"><pre class=\"bash hljs\">" + item.'.$field.' + "</pre></div>";
                        }';
                        $output .= $add_js;
                        $output .= ",\n".'onSelect: function (e, term, item) {
                                data_key = $(item).attr("data-key");
                                if (data_key)
                                    $("#'.$name.'").val(data_key);
                                else
                                    $("#'.$name.'").val(item.text());

                                $("#'.$attributes['id'].'").val(item.text());
                            }'."\n";
                    $output .= '});';
                    if ($has_separate_hidden)
                    {
                        $output .= '$("#'.$slashed_id.'").on("keyup", function () {
                            $("#'.$different_id.'").text($("#'.$slashed_id.'").val());
                        });';
                    }
                    $output .= (!$jquery_autocomplete_loaded ? 'head.load("'.asset('/bower_components/jquery-auto-complete/jquery.auto-complete.css').'");' : '')."\n";
                    $output .= (!$jquery_autocomplete_loaded ? 'head.load("'.asset('/css/selectize.css').'");' : '')."\n";
                    $output .= (!$jquery_autocomplete_loaded ? 'head.load("'.asset('/bower_components/highlightjs/styles/default.css').'");' : '')."\n";
                    $output .= (!$jquery_autocomplete_loaded ? 'head.load("'.asset('/bower_components/highlightjs/highlight.pack.min.js').'");' : '')."\n";
                    $output .= '$(function () {
                        $("pre").each(function(i, block) {
                            hljs.highlightBlock(block);
                        });
                    });';
                $output .= '</script>';
                Request::merge(['jquery_autocomplete_loaded' => true]);
            }
            else
            {
                $jquery_textcomplete_loaded= Request::get('jquery_textcomplete_loaded', false);
                $output .= '<script>
                head.load("'.asset('/bower_components/jquery-textcomplete/dist/jquery.textcomplete.js').'", function () {
                    var elements = ["span", "div", "h1", "h2", "h3"];
                    $("#'.$slashed_id.'").textcomplete([{
                        match: /.*(.{1,}).*$/g,
                        cache: true,
                        index: 0,
                        search: function (term, callback, match) {
                            $.getJSON("'.$url.'", { query: term, field: "'.$field.'" })
                                .done(function (resp) {
                                    lines = [];
                                    for(i=0; i < resp.results.length; ++i)
                                    {
                                        db_lines = resp.results[i].text.split("\\\\n");
                                        trimmed_db_lines = [];
                                        for (c=0; c < db_lines.length; ++c)
                                        {
                                            if (db_lines[c].trim().indexOf(term) != -1)
                                                lines.push(db_lines[c].trim());
                                        }
                                    }
                                    prev_term = "";
                                    callback(lines);
                                })
                                .fail(function () {
                                    callback([]);
                                });
                        },
                        replace: function (value) {
                            return value;
                        },
                    }],
                    {
                        debounce: 250,
                        onKeydown: function (e, commands) {
                            if (e.ctrlKey && e.keyCode === 74) { // CTRL-J
                                return commands.KEY_ENTER;
                            }
                        }
                    });'."\n";
                    if ($has_separate_hidden)
                    {
                        $output .= '$("#'.$slashed_id.'").on("keyup change", function () {
                            $("#'.$different_id.'").text($("#'.$slashed_id.'").val());
                        });';
                        $output .= '$("#'.$different_id.'").on("keyup change", function () {
                            $("#'.$slashed_id.'").text($("#'.$different_id.'").val());
                        });';
                    }
                $output .= "\n".'});</script>';
                Request::merge(['jquery_textcomplete_loaded' => true]);
            }
        }

        return $output;
    }
}
