/**
 * Internal dependencies
 */
import FormSkeleton from './components/FormSkeleton';
import LoadError from './components/LoadError';
import Notices from './components/Notices';
import { CAN_MANAGE_SETTINGS } from './data/boot';
import useSettingsStore from './data/useSettingsStore';
import Footer from './shell/Footer';
import navMeta from './shell/navMeta';
import SideNav from './shell/SideNav';
import TopBar from './shell/TopBar';
import { GROUPS, TABS } from './shell/tabs';
import useSaveShortcut from './shell/useSaveShortcut';
import useTab from './shell/useTab';
import useUnsavedWarning from './shell/useUnsavedWarning';
import AccessTab from './tabs/AccessTab';
import AdvancedTab from './tabs/AdvancedTab';
import EnrollmentsTab from './tabs/enrollments/EnrollmentsTab';
import OverviewTab from './tabs/overview/OverviewTab';
import QuizResultsTab from './tabs/quiz-results/QuizResultsTab';
import QuizzesTab from './tabs/QuizzesTab';
import WooCommerceTab from './tabs/WooCommerceTab';

// Settings tabs render from the settings store (skeleton while it loads).
const SETTINGS_TABS = {
	access: AccessTab,
	quizzes: QuizzesTab,
	woocommerce: WooCommerceTab,
	advanced: AdvancedTab,
};

// Data tabs load their own data.
const DATA_TABS = {
	overview: OverviewTab,
	enrollments: EnrollmentsTab,
	'quiz-results': QuizResultsTab,
};

// A ?tab= link opens that tab.
const INITIAL_TAB =
	new URLSearchParams( window.location.search ).get( 'tab' ) || 'overview';

/**
 * Shell + one settings store: every settings tab edits it and one Save (top
 * bar or Cmd/Ctrl+S) writes all changed options atomically. On the data
 * tabs the top bar only offers Save while settings edits are pending.
 */
export default function App() {
	const settings = useSettingsStore( CAN_MANAGE_SETTINGS );
	const tab = useTab(
		TABS.map( ( t ) => t.id ),
		INITIAL_TAB
	);
	const current = TABS.find( ( t ) => t.id === tab ) || TABS[ 0 ];
	const target =
		settings.data && ( current.save || settings.hasEdits )
			? settings
			: null;

	useUnsavedWarning( settings.hasEdits );
	useSaveShortcut(
		() => target?.save(),
		!! target && target.hasEdits && ! target.isSaving,
		!! target
	);

	let content;
	if ( DATA_TABS[ current.id ] ) {
		const Tab = DATA_TABS[ current.id ];
		content = <Tab />;
	} else if ( settings.error ) {
		content = (
			<LoadError message={ settings.error } onRetry={ settings.reload } />
		);
	} else if ( settings.isLoading ) {
		content = <FormSkeleton />;
	} else {
		const Tab = SETTINGS_TABS[ current.id ];
		content = <Tab store={ settings } />;
	}

	return (
		<>
			<div className="lw-admin-shell">
				<SideNav
					tabs={ TABS }
					groups={ GROUPS }
					current={ current.id }
					meta={ navMeta( {
						errors: settings.errors,
						options: settings.data ? settings.saved : null,
					} ) }
				/>
				<div className="lw-admin-main">
					<TopBar title={ current.title } store={ target } />
					<main className="lw-admin-scroll">
						<div className="lw-admin-content">{ content }</div>
					</main>
					<Footer />
				</div>
			</div>
			<Notices />
		</>
	);
}
