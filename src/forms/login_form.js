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
 * Show login form.
 *
 * @param errors
 * @param url
 * @param setUrl
 * @param login
 * @param setLogin
 * @param password
 * @param setPassword
 * @param setEnabled
 * @param saveCredentials
 * @param setSaveCredentials
 * @returns {JSX.Element}
 * @constructor
 */
export const EDLFW_LOGIN_FORM = ( { config, loadTree, setLoadTree, errors, url, setUrl, login, setLogin, password, setPassword, setEnabled, saveCredentials, setSaveCredentials } ) => {
    /**
     * Handle the login itself.
     */
    function do_login() {
        // bail if one setting is not given.
        if( ! url || ! login || ! password ) {
            return;
        }

        // enable the listing.
        setLoadTree( ! loadTree )
        setEnabled( true )
    }

    return (
        <>
            <h2>{edlfwJsVars.form_login.title}</h2>
            {edlfwJsVars.form_login.description.length > 0 && <p>{edlfwJsVars.form_login.description}</p>}
            {errors && <EDLFW_ERRORS errors={errors}/>}
            <InputControl label={edlfwJsVars.form_login.url.label} value={url} onChange={(value) => setUrl( value )}/>
            <InputControl label={edlfwJsVars.form_login.login.label} value={login} onChange={(value) => setLogin( value )}/>
            <InputControl label={edlfwJsVars.form_login.password.label} type={"password"} value={password} onChange={(value) => setPassword( value )}/>
            {config.archive && <ToggleControl
                __nextHasNoMarginBottom
                label={edlfwJsVars.form_login.save_credentials.label}
                checked={ saveCredentials }
                onChange={ (newValue) => {
                    setSaveCredentials( newValue );
                } }
            />}
            <Button __next40pxDefaultSize variant="primary" onClick={() => do_login()} disabled={ url.length === 0 || login.length === 0 || password.length === 0 }>{edlfwJsVars.form_login.button.label}</Button>
        </>
    )
}
