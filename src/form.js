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
import { useRef, useState } from "@wordpress/element";
import apiFetch from '@wordpress/api-fetch';
import {EDLFW_ERRORS} from "./errors";
import { edlfw_is_field_filled, edlfw_check_field_groups, edlfw_is_form_completed } from './helper';

/**
 * Return a translated text of the form with fallback.
 *
 * @param key The key of the text in "form_file".
 * @param fallback The fallback text.
 * @returns {string}
 */
const edlfw_form_text = ( key, fallback ) => {
    if( typeof edlfwJsVars.form_file !== 'undefined' && edlfwJsVars.form_file[key] ) {
        return edlfwJsVars.form_file[key];
    }
    return fallback;
}

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
    // the chosen files of fields with type "file", which are not uploaded yet (field name as key).
    const pendingFiles = useRef( {} );

    // marker for running upload.
    const [ uploading, setUploading ] = useState( false );

    // keys to reset the file inputs after removing the chosen file (field name as key).
    const [ resetKeys, setResetKeys ] = useState( {} );

    /**
     * Return whether the option to save the entry as archive should be visible.
     *
     * If "archive_requires_field" is set in the configuration, the named field must be filled.
     *
     * @returns {boolean}
     */
    function is_archive_allowed() {
        if( ! config.archive ) {
            return false;
        }
        if( ! config.archive_requires_field ) {
            return true;
        }
        return edlfw_is_field_filled( config.fields[config.archive_requires_field] );
    }

    /**
     * Upload the chosen files of fields with type "file".
     *
     * Each file is uploaded via the REST API endpoint configured in the field (default: media library of WordPress).
     * The ID from the response is used as value of the field.
     *
     * @returns {Promise<boolean>} True if all uploads were successfully.
     */
    async function upload_files() {
        for ( const field of Object.keys( pendingFiles.current ) ) {
            // get the chosen file.
            const file = pendingFiles.current[field];

            // bail if no file is chosen.
            if( ! file ) {
                continue;
            }

            // create the request body.
            const body = new FormData();
            body.append( 'file', file, file.name );

            try {
                // upload the file.
                const response = await apiFetch( { path: config.fields[field].upload_path ?? '/wp/v2/media', method: 'POST', body: body } );

                // bail if response does not contain an ID.
                if( ! response || ! response.id ) {
                    setErrors( [ edlfw_form_text( 'upload_error', 'The file could not be uploaded.' ) ] );
                    return false;
                }

                // use the ID as value of this field.
                config.fields[field].value = String( response.id );
                config.fields[field].file_name = file.name;
                pendingFiles.current[field] = false;
            } catch ( err ) {
                // show the error.
                let upload_errors = [ edlfw_form_text( 'upload_error', 'The file could not be uploaded.' ) ];
                if ( err && err.message ) {
                    upload_errors.push( err.message );
                }
                setErrors( upload_errors );
                return false;
            }
        }

        // return true as all files are uploaded.
        return true;
    }

    /**
     * Handle the login itself.
     */
    async function do_login() {
        setErrors( false );

        // bail if more than one field of a group is filled.
        const groups = edlfw_check_field_groups( config.fields, pendingFiles.current );
        if( groups.multiple.length > 0 ) {
            // get the error text from the first field of each group.
            let group_errors = [];
            groups.multiple.forEach( group => {
                const name = Object.keys( config.fields ).find( field => config.fields[field].one_of === group );
                group_errors.push( config.fields[name].one_of_error ?? edlfw_form_text( 'one_of_multiple', 'Please fill in only one of the fields.' ) );
            } );
            setErrors( group_errors );
            return;
        }

        // do not save the entry if this is not allowed.
        if( saveCredentials && ! is_archive_allowed() ) {
            setSaveCredentials( false );
        }

        // upload chosen files.
        if( Object.values( pendingFiles.current ).some( file => file ) ) {
            setUploading( true );
            const uploaded = await upload_files();
            setUploading( false );
            if( ! uploaded ) {
                return;
            }
        }

        // load the tree.
        setLoadTree( ! loadTree );
        setEnabled( true );
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
     * Set the chosen file of a field with the type "file".
     *
     * @param field
     * @param file
     */
    function setFile( field, file ) {
        pendingFiles.current[field] = file ? file : false;

        // a new chosen file replaces an already uploaded file.
        config.fields[field].value = '';
        config.fields[field].file_name = file ? file.name : '';

        // reset the file input if the file has been removed.
        if( ! file ) {
            setResetKeys( { ...resetKeys, [field]: ( resetKeys[field] ?? 0 ) + 1 } );
        }
        setUpdated( ! updated );
    }

    return (
        <>
            <h2>{config.form_title}</h2>
            {config.form_description && config.form_description.length > 0 && <p dangerouslySetInnerHTML={{__html: config.form_description}} />}
            {errors && <EDLFW_ERRORS errors={errors}/>}
            <form autoComplete="off" method="post" action="">
                {Object.keys(config.fields).map( field => {
                    return(
                        <div key={field + '_wrapper'} className={config.fields[field].one_of ? 'edlfw-field-group-' + config.fields[field].one_of : ''}>
                            {config.fields[field].type === 'password' && <InputControl key={field} label={config.fields[field].label} type={config.fields[field].type} placeholder={config.fields[field].placeholder} value={config.fields[field].value} onChange={(value) => setField( field, value )} __next40pxDefaultSize={true} disabled={config.fields[field].readonly} />}
                            {config.fields[field].type === 'text' && <InputControl key={field} label={config.fields[field].label} type={config.fields[field].type} placeholder={config.fields[field].placeholder} value={config.fields[field].value} onChange={(value) => setField( field, value )} __next40pxDefaultSize={true} disabled={config.fields[field].readonly} />}
                            {config.fields[field].type === 'url' && <InputControl key={field} label={config.fields[field].label} type={config.fields[field].type} placeholder={config.fields[field].placeholder} value={config.fields[field].value} onChange={(value) => setField( field, value )} __next40pxDefaultSize={true} disabled={config.fields[field].readonly} />}
                            {config.fields[field].type === 'textarea' && <TextareaControl key={field} label={config.fields[field].label} placeholder={config.fields[field].placeholder} value={config.fields[field].value} onChange={(value) => setField( field, value )} __nextHasNoMarginBottom={true} disabled={config.fields[field].readonly} />}
                            {config.fields[field].type === 'checkbox' && <ToggleControl key={field} label={config.fields[field].label} checked={config.fields[field].value} onChange={(value) => setField( field, value )} __nextHasNoMarginBottom={true} disabled={config.fields[field].readonly} />}
                            {config.fields[field].type === 'select' && <SelectControl key={field} label={config.fields[field].label} placeholder={config.fields[field].placeholder} value={config.fields[field].value} onChange={(value) => setField( field, value )} __next40pxDefaultSize={true} __nextHasNoMarginBottom={true} disabled={config.fields[field].readonly} options={config.fields[field].options} />}
                            {config.fields[field].type === 'file' && <div className="components-base-control edlfw-file-field">
                                <label className="components-base-control__label" htmlFor={'edlfw-file-' + field}>{config.fields[field].label}</label>
                                <input id={'edlfw-file-' + field} key={field + '_' + ( resetKeys[field] ?? 0 )} type="file" accept={config.fields[field].accept} disabled={config.fields[field].readonly || uploading} onChange={(event) => setFile( field, event.target.files && event.target.files[0] ? event.target.files[0] : false )} />
                                {config.fields[field].file_name && ! pendingFiles.current[field] && edlfw_is_field_filled( config.fields[field] ) && <p className="edlfw-file-uploaded">{edlfw_form_text( 'uploaded', 'Uploaded file:' )} <strong>{config.fields[field].file_name}</strong></p>}
                                {config.fields[field].file_name && <Button variant="link" onClick={() => setFile( field, false )}>{edlfw_form_text( 'remove', 'Remove file' )}</Button>}
                            </div>}
                            {config.fields[field].description && <p key={field + '_description'} dangerouslySetInnerHTML={{__html: config.fields[field].description}} />}
                        </div>
                    );
                })}
                {is_archive_allowed() && <ToggleControl
                    __nextHasNoMarginBottom
                    label={edlfwJsVars.form_login.save_credentials.label}
                    checked={ saveCredentials }
                    onChange={ (newValue) => {
                        setSaveCredentials( newValue );
                    } }
                />}
                {uploading && <p className="edlfw-uploading">{edlfw_form_text( 'uploading', 'Uploading file …' )}</p>}
                <Button __next40pxDefaultSize variant="primary" onClick={() => do_login()} disabled={ uploading || ! edlfw_is_form_completed( config.fields, pendingFiles.current ) } isBusy={ uploading }>{edlfwJsVars.form_login.button.label}</Button>
            </form>
        </>
    )
}
