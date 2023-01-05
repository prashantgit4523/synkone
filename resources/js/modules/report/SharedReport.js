import './style.scss'
import '../../layouts/app-layout/header/header.scss'
import {useEffect, useState, Fragment} from "react";
import { Link, usePage } from "@inertiajs/inertia-react";
import ShareLinkModal from "./components/ShareLinkModal";
import FlashMessages from "../../common/FlashMessages";
import {useSelector} from "react-redux";
import Sidebar from "./components/Sidebar";
import SidebarLogo from "./components/SidebarLogo";
import ReportContent from "./components/ReportContent";

function SharedReport(props) {
    const { globalSetting, APP_URL, authCheck} = usePage().props;
    
    const [modalShown, setModalShown] = useState(false);
    const [categories, setCategories] = useState([]);
    const [report, setReport] = useState();
    const [topDepartment, setTopDepartment] = useState(false);

    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);

    useEffect(() => {
        document.title = "Report";
        fetchCategories();
    },[]);

    useEffect(() => {
        const data = appDataScope.split('-');
        
        if(data[1] == 0){
            setTopDepartment(true);
        }

    },[appDataScope]);

    useEffect(() => {
            axiosFetch(route('report.reportData'))
            .then((res) => {
                setReport(res.data);
            });
    },[modalShown]);

    const fetchCategories = () => {
        axiosFetch
        .post(route("report.categoryData"), {'data_scope': appDataScope})
        .then((res) => {
            setCategories(res.data);
        });
    }

    return (
        <Fragment>
        {modalShown && <ShareLinkModal
            show={modalShown}
            onClose={() => setModalShown(false)}
            report={report}
        />}
            <header id="topnav" className="primary-bg-color noPrint">
                {/* Topbar Start */}
                <div className="navbar-custom navbar-header">
                    <div className="container-fluid">

                        <div className="logo-box">
                            <Link
                                href="#"
                                className="logo text-center"
                            >
                                <span className="logo">
                                     <img
                                            src={APP_URL + "assets/images/cyberarrow.png"}
                                            alt="Logo"
                                        />
                                </span>
                            </Link>
                        </div>

                        <div className="icon-box">
                            <div className="logo text-center">
                            <span className="logo">
                            {authCheck && topDepartment && report && <i className="fe-share-2 report-icon" onClick={() => {setModalShown(true)}} title="Share report"></i>}
                            <i className="fe-printer report-icon" onClick={() => window.print()} title="Print report"></i>
                            </span>
                            </div>
                        </div>
                    </div>
                    {/* end of container-fluid */}
                </div>
                {/* end Topbar */}
                <div className="topbar-menu">
                    <div className="container">
                        <div className="clearfix" />
                    </div>
                </div>
                {/* end navbar-custom */}
            </header>

            <div className="wrapper mb-5">
                <div className="container-fluid">
                    <div id="content-section-wp">
            <div id="integration-page">
                <div className="row">
                    <div className="col-sm-7">
                        {/* Flash messese */}
                        <FlashMessages/>
                    </div>

                    <div className="col-sm-5"></div>
                </div>
                <div className="row noPrint" id="noPrint">
                    {categories && <Sidebar categories={categories} customClass={"mobile-only"}/>}

                    {categories && <ReportContent categories={categories} globalSetting={globalSetting} printable={false} widthClass="col-sm-7 card"/>}
                    
                    {categories && <Sidebar categories={categories} customClass={"desktop-only"}/>}
                </div>
                <div className="printableContent">
                    <div className="col-sm-12">
                        <SidebarLogo/>
                    </div>
                    <div className="row" id="printableContent">
                        {categories && <ReportContent categories={categories} globalSetting={globalSetting} printable={true} widthClass="col-sm-12"/>}
                    </div>
                </div>
            </div>
                    </div>
                    {/* END content-section-wp */}
                    <div className="clearfix" >
                    </div>
                </div>{/* container fluid */}
            </div>

            <footer className="footer noPrint">
                <div className="footer-container">
                    <div className="footer-content">
                    <div className="u__cf" dir="ltr">
                        <div className="footer__logo">
                        <a href="#">
                        <img
                            src={APP_URL +"assets/images/cyberarrow.png"}
                            alt="Logo"
                        />
                        </a>
                        </div>
                    </div>
                    </div>
                </div>
            </footer>
        </Fragment>
    );
}

export default SharedReport;