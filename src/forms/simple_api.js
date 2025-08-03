/**
 * Import dependencies.
 */
import {
    Button,
    ToggleControl,
    __experimentalInputControl as InputControl
} from '@wordpress/components';
import {EDLFW_ERRORS} from "../errors";

/**
 * Show simple API form.
 *
 * @param errors
 * @param api_key
 * @param setApiKey
 * @param setEnabled
 * @param saveCredentials
 * @param setSaveCredentials
 * @returns {JSX.Element}
 * @constructor
 */
export const EDLFW_SIMPLE_API_FORM = ( { config, loadTree, setLoadTree, errors, setErrors, apiKey, setApiKey, setEnabled, url, setUrl, saveCredentials, setSaveCredentials } ) => {
    /**
     * Handle the login itself.
     */
    function do_login() {
        // bail if one setting is not given.
        if( ! apiKey ) {
            return;
        }

        // enable the listing.
        setErrors( false )
        setLoadTree( ! loadTree )
        setEnabled( true );
    }

    return (
        <>
            <h2>{edlfwJsVars.form_api.title}</h2>
            {edlfwJsVars.form_api.description.length > 0 && <p>{edlfwJsVars.form_api.description}</p>}
            {errors && <EDLFW_ERRORS errors={errors}/>}
            <InputControl label={edlfwJsVars.form_api.url.label} value={url} onChange={(value) => setUrl( value )}/>
            <InputControl label={edlfwJsVars.form_api.key.label} value={apiKey} onChange={(value) => setApiKey( value )}/>
            {config.archive && <ToggleControl
                __nextHasNoMarginBottom
                label={edlfwJsVars.form_api.save_credentials.label}
                checked={ saveCredentials }
                onChange={ (newValue) => {
                    setSaveCredentials( newValue );
                } }
            />}
            <Button variant="primary" onClick={() => do_login()} disabled={ url.length === 0 || apiKey.length === 0 }>{edlfwJsVars.form_api.button.label}</Button>
        </>
    )
}
