/**
 * Import dependencies.
 */
import {
    Button,
    ToggleControl,
    __experimentalInputControl as InputControl,
    SelectControl,
    TextareaControl
} from '@wordpress/components';
import {EDLFW_ERRORS} from "./errors";

/**
 * Show login form.
 *
 * @param config
 * @param loadTree
 * @param setLoadTree
 * @param errors
 * @param setEnabled
 * @param saveCredentials
 * @param setSaveCredentials
 * @returns {JSX.Element}
 * @constructor
 */
export const EDLFW_FORM = ( { config, loadTree, setLoadTree, errors, setErrors, setEnabled, saveCredentials, setSaveCredentials, updated, setUpdated } ) => {
    /**
     * Handle the login itself.
     */
    function do_login() {
        setErrors( false )
        setLoadTree( ! loadTree )
        setEnabled( true )
    }

    /**
     * Set the field value.
     *
     * @param field
     * @param value
     */
    function setField( field, value ) {
        config.fields[field].value = value;
        setUpdated( ! updated );
    }

    /**
     * Return whether all fields are set.
     *
     * @returns {boolean}
     */
    function is_completed() {
        let counter = 0;
        let fields = 0;
        Object.keys(config.fields).map( field => {
            // bail if "not_required" is set.
            if( ! config.fields[field].not_required ) {
                fields++;
                if (config.fields[field].value && config.fields[field].value.length > 0) {
                    counter++;
                }
            }
        });
        return fields === counter;
    }

    return (
        <>
            <h2>{config.form_title}</h2>
            {config.form_description && config.form_description.length > 0 && <p dangerouslySetInnerHTML={{__html: config.form_description}} />}
            {errors && <EDLFW_ERRORS errors={errors}/>}
            <form autoComplete="off" method="post" action="">
                {Object.keys(config.fields).map( field => {
                    return(
                        <div key={field + '_wrapper'}>
                            {config.fields[field].type === 'password' && <InputControl key={field} label={config.fields[field].label} type={config.fields[field].type} placeholder={config.fields[field].placeholder} value={config.fields[field].value} onChange={(value) => setField( field, value )} __next40pxDefaultSize={true} disabled={config.fields[field].readonly} />}
                            {config.fields[field].type === 'text' && <InputControl key={field} label={config.fields[field].label} type={config.fields[field].type} placeholder={config.fields[field].placeholder} value={config.fields[field].value} onChange={(value) => setField( field, value )} __next40pxDefaultSize={true} disabled={config.fields[field].readonly} />}
                            {config.fields[field].type === 'url' && <InputControl key={field} label={config.fields[field].label} type={config.fields[field].type} placeholder={config.fields[field].placeholder} value={config.fields[field].value} onChange={(value) => setField( field, value )} __next40pxDefaultSize={true} disabled={config.fields[field].readonly} />}
                            {config.fields[field].type === 'textarea' && <TextareaControl key={field} label={config.fields[field].label} placeholder={config.fields[field].placeholder} value={config.fields[field].value} onChange={(value) => setField( field, value )} __nextHasNoMarginBottom={true} disabled={config.fields[field].readonly} />}
                            {config.fields[field].type === 'select' && <SelectControl key={field} label={config.fields[field].label} placeholder={config.fields[field].placeholder} value={config.fields[field].value} onChange={(value) => setField( field, value )} __next40pxDefaultSize={true} __nextHasNoMarginBottom={true} disabled={config.fields[field].readonly} options={config.fields[field].options} />}
                            {config.fields[field].description && <p key={field + '_description'} dangerouslySetInnerHTML={{__html: config.fields[field].description}} />}
                        </div>
                    );
                })}
                {config.archive && <ToggleControl
                    __nextHasNoMarginBottom
                    label={edlfwJsVars.form_login.save_credentials.label}
                    checked={ saveCredentials }
                    onChange={ (newValue) => {
                        setSaveCredentials( newValue );
                    } }
                />}
                <Button __next40pxDefaultSize variant="primary" onClick={() => do_login()} disabled={ ! is_completed() }>{edlfwJsVars.form_login.button.label}</Button>
            </form>
        </>
    )
}
