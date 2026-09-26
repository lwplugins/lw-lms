/**
 * WordPress dependencies
 */
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import FieldErrors, {
	describedBy,
	useDescribedByRef,
} from '../../components/FieldErrors';
import Section from '../../components/Section';
import { api, errorMessage, fieldErrors } from '../../data/api';
import { TODAY } from '../../data/boot';
import postOptions from '../../data/courseOptions';
import UserPicker from './UserPicker';

const EMPTY = { user: '', course: '', expires: '' };

// Fixed ids, so the fields can point aria-describedby at their messages.
const COURSE_ERRORS = 'lw-lms-grant-course-errors';
const EXPIRES = 'lw-lms-grant-expires';
const EXPIRES_ERRORS = 'lw-lms-grant-expires-errors';

/**
 * Enroll a learner in a course by hand (manual source). Enrolling someone
 * who is already enrolled by hand updates that enrollment (e.g. its end date).
 *
 * @param {Object}     props
 * @param {Object[]}   props.courses   Course lookup rows.
 * @param {() => void} props.onGranted Called after a successful grant.
 */
export default function GrantForm( { courses, onGranted } ) {
	const [ form, setForm ] = useState( EMPTY );
	const [ errors, setErrors ] = useState( {} );
	const [ isSaving, setIsSaving ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );
	const hasError = ( key ) => !! errors[ key ]?.length;
	const courseRef = useDescribedByRef(
		hasError( 'course' ) ? COURSE_ERRORS : undefined
	);
	const set = ( key, value ) => {
		setForm( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setErrors( ( prev ) => ( { ...prev, [ key ]: undefined } ) );
	};

	const submit = async ( event ) => {
		event.preventDefault();
		setIsSaving( true );
		try {
			const result = await api.grant( {
				user_id: parseInt( form.user, 10 ) || 0,
				course_id: parseInt( form.course, 10 ) || 0,
				expires: form.expires || null,
			} );
			const row = result.enrollment;
			createSuccessNotice(
				row?.user && row?.course
					? sprintf(
							/* translators: 1: learner name, 2: course title. */
							__( '%1$s is enrolled in %2$s.', 'lw-lms' ),
							row.user.name,
							row.course.title
						)
					: __( 'Enrollment saved.', 'lw-lms' ),
				{ type: 'snackbar' }
			);
			setForm( EMPTY );
			setErrors( {} );
			onGranted();
		} catch ( e ) {
			const fields = fieldErrors( e );
			if ( fields ) {
				setErrors( {
					user: fields.user_id,
					course: fields.course_id,
					expires: fields.expires,
				} );
			}
			createErrorNotice(
				fields
					? __(
							'Fix the highlighted fields and try again.',
							'lw-lms'
						)
					: errorMessage( e ),
				{ type: 'snackbar' }
			);
		}
		setIsSaving( false );
	};

	return (
		<Section
			title={ __( 'Enroll a learner', 'lw-lms' ) }
			description={ __(
				'Gives the learner access to the course, whatever its access type. Enrolling them again changes the end date.',
				'lw-lms'
			) }
		>
			<form className="lw-lms-grant" onSubmit={ submit }>
				<div className="lw-lms-grant__fields">
					<div>
						<UserPicker
							value={ form.user }
							errors={ errors.user }
							onChange={ ( value ) => set( 'user', value ) }
						/>
					</div>
					<div>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							ref={ courseRef }
							aria-invalid={ hasError( 'course' ) || undefined }
							label={ __( 'Course', 'lw-lms' ) }
							value={ form.course }
							options={ postOptions(
								courses,
								__( 'Select a course', 'lw-lms' )
							) }
							onChange={ ( value ) => set( 'course', value ) }
						/>
						<FieldErrors
							errors={ errors.course }
							id={ COURSE_ERRORS }
						/>
					</div>
					<div>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							type="date"
							id={ EXPIRES }
							aria-describedby={ describedBy(
								`${ EXPIRES }__help`,
								hasError( 'expires' ) && EXPIRES_ERRORS
							) }
							aria-invalid={ hasError( 'expires' ) || undefined }
							min={ TODAY }
							label={ __( 'Access ends (optional)', 'lw-lms' ) }
							help={ __(
								'Leave empty for lifetime access. Access ends at the end of that day.',
								'lw-lms'
							) }
							value={ form.expires }
							onChange={ ( value ) => set( 'expires', value ) }
						/>
						<FieldErrors
							errors={ errors.expires }
							id={ EXPIRES_ERRORS }
						/>
					</div>
				</div>
				<div>
					<Button
						__next40pxDefaultSize
						variant="primary"
						type="submit"
						isBusy={ isSaving }
						disabled={ isSaving || ! form.user || ! form.course }
						accessibleWhenDisabled
					>
						{ __( 'Enroll', 'lw-lms' ) }
					</Button>
				</div>
			</form>
		</Section>
	);
}
