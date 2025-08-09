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
export const EDLFW_AWS_S3_FORM = ( { config, loadTree, setLoadTree, errors, login, setLogin, password, setPassword, apiKey, setApiKey, setEnabled, url, setUrl, saveCredentials, setSaveCredentials } ) => {
    /**
     * Handle the login itself.
     */
    function do_login() {
        // bail if one setting is not given.
        if( ! login || ! password || ! apiKey ) {
            return;
        }

        // enable the listing.
        setLoadTree( ! loadTree )
        setEnabled( true );
    }

    return (
        <>
            <h2>{edlfwJsVars.form_api.title}</h2>
            {edlfwJsVars.form_api.description.length > 0 && <p>{edlfwJsVars.form_api.description}</p>}
            {errors && <EDLFW_ERRORS errors={errors}/>}
            <InputControl label={edlfwJsVars.aws_s3_api.access_key.label} value={login} onChange={(value) => setLogin( value )}/>
            <InputControl label={edlfwJsVars.aws_s3_api.secret_key.label} value={password} onChange={(value) => setPassword( value )}/>
            <InputControl label={edlfwJsVars.aws_s3_api.bucket.label} value={apiKey} onChange={(value) => setApiKey( value )}/>
            {config.archive && <ToggleControl
                __nextHasNoMarginBottom
                label={edlfwJsVars.form_api.save_credentials.label}
                checked={ saveCredentials }
                onChange={ (newValue) => {
                    setSaveCredentials( newValue );
                } }
            />}
            <Button variant="primary" onClick={() => do_login()} disabled={ login.length === 0 || password.length === 0 || apiKey.length === 0 }>{edlfwJsVars.form_api.button.label}</Button>
        </>
    )
}
