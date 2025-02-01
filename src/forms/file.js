/**
 * Import dependencies.
 */
import {
  Button,
  __experimentalInputControl as InputControl
} from '@wordpress/components';
import {EDLFW_ERRORS} from "../errors";

/**
 * Show simple API form.
 *
 * @param errors
 * @param api_key
 * @param setEnabled
 * @returns {JSX.Element}
 * @constructor
 */
export const EDLFW_FILE_FORM = ( { errors, setEnabled, url, setUrl } ) => {
  /**
   * Handle the login itself.
   */
  function do_login() {
    // bail if one setting is not given.
    if( ! url ) {
      return;
    }

    // enable the listing.
    setEnabled( true )
  }

  return (
    <>
      <h2>{edlfwJsVars.form_file.title}</h2>
      {edlfwJsVars.form_file.description.length > 0 && <p>{edlfwJsVars.form_file.description}</p>}
      {errors && <EDLFW_ERRORS errors={errors}/>}
      <InputControl label={edlfwJsVars.form_file.url.label} value={url} onChange={(value) => setUrl( value )}/>
      <Button variant="primary" onClick={() => do_login()} disabled={ url.length === 0 }>{edlfwJsVars.form_file.button.label}</Button>
    </>
  )
}
