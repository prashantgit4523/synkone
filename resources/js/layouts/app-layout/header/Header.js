import React, { Fragment, useState } from "react";
import ProfileDropDown from "./components/profile-dropdown/ProfileDropDown";
import DataScopeDropdown from "./components/data-scope-dropdown/DataScopeDropdown";
import { Link, usePage } from "@inertiajs/inertia-react";
import NavigarionMenu from "./components/navigation-menu/NavigarionMenu";
import "./header.scss";

function Header(props) {
    const { authUserRoles, globalSetting, APP_URL, file_driver } = usePage().props;
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    let logoRedirectRoute = "#";
    if (authUserRoles.includes("Global Admin")) {
        logoRedirectRoute = route("global.dashboard");
    } else {
        if (authUserRoles.includes("Contributor")) {
            logoRedirectRoute = route("compliance-dashboard");
        }
    }

    /* Handle mobile menu toggle */
    const handleMobileMenuToggle = () => {
        setIsMobileMenuOpen(!isMobileMenuOpen);
    };


    const handleHelpBarToggle = () => {
        document.body.classList.toggle('right-bar-enabled')
    }

    const openIntercom =() =>{
        document.body.classList.toggle('right-bar-enabled');
        window.Intercom('update', {
            "hide_default_launcher": false
        });
        window.Intercom('show');
    }

    return (
        <Fragment>
            {/* Navigation Bar*/}
            <header id="topnav" className="primary-bg-color">
                {/* Topbar Start */}
                <div className="navbar-custom">
                    <div className="container-fluid">
                        <ul className="list-unstyled topnav-menu float-end mb-0">
                            <li className="dropdown notification-list">
                                {/* Mobile menu toggle*/}
                                <a
                                    className={`navbar-toggle nav-link ${isMobileMenuOpen ? "open" : ""
                                        }`}
                                    onClick={() => {
                                        handleMobileMenuToggle();
                                    }}
                                >
                                    <div className="lines">
                                        <span />
                                        <span />
                                        <span />
                                    </div>
                                </a>
                                {/* End mobile menu toggle*/}
                            </li>
                            <ProfileDropDown></ProfileDropDown>
                            <li className="notifiation-list help-icon">
                                <a className="nav-link cursor-pointer" onClick={handleHelpBarToggle}>
                                    <i className="mdi mdi-help-circle-outline"></i>
                                </a>
                            </li>
                        </ul>

                        {/* data-scope-dropdown */}
                        <DataScopeDropdown id="TopDataScopeDropdown"></DataScopeDropdown>
                        {/* LOGO */}

                        <div className="logo-box">
                            <Link
                                href={logoRedirectRoute}
                                className="logo text-center"
                            >
                                <span className="logo">
                                    {file_driver == "s3" ?
                                        <img
                                            src={globalSetting.company_logo === "assets/images/ebdaa-Logo.png" ? APP_URL + globalSetting.company_logo : globalSetting.company_logo}
                                            alt="Company Logo"
                                            width={70}
                                        />
                                        :
                                        <img
                                            src={globalSetting.company_logo === "assets/images/ebdaa-Logo.png" ? APP_URL + globalSetting.company_logo : asset(globalSetting.company_logo)}
                                            alt="Company Logo"
                                            width={70}
                                        />
                                    }
                                    <span className="logo-lg-text-light secondary-text-color">
                                        {decodeHTMLEntity(
                                            globalSetting.display_name
                                        )}
                                    </span>
                                </span>
                            </Link>
                        </div>
                    </div>
                    {/* end of container-fluid */}
                </div>
                {/* end Topbar */}
                <div className="topbar-menu">
                    <div className="container">
                        <NavigarionMenu
                            authUserRoles={authUserRoles}
                            isMobileMenuOpen={isMobileMenuOpen}
                        ></NavigarionMenu>
                        <div className="clearfix" />
                    </div>
                </div>
                {/* end navbar-custom */}
            </header>
            <div className="right-bar">
                <div className="rightbar-title">
                    <a className="right-bar-toggle float-end cursor-pointer" onClick={handleHelpBarToggle}>
                        <i className="dripicons-cross noti-icon"></i>
                    </a>
                    <h5 className="m-0 text-white">Help and Feedback</h5>
                </div>
                <div className="py-3 px-2">
                    <div className="border-top pt-1">
                        <div className="py-1 border-bottom d-flex">
                            <div>
                                <i className="mdi mdi-face-agent fs-3 me-1 text-muted"></i>
                            </div>
                            <div onClick={openIntercom}>
                                <h6 className="font-14 pointer-cursor" >Live Chat with Us</h6>
                                <p className="pointer-cursor">Get instant help, our experts are ready to answer questions regarding CyberArrow functions and features.</p>
                            </div>
                        </div>
                        <div className="py-1 border-bottom d-flex">
                            <div>
                                <i className="mdi mdi-lifebuoy fs-3 me-1 text-muted"></i>
                            </div>
                            <div>
                                <a href="https://help.cyberarrowgrc.io/" target="_blank"> <h6 className="font-14">Visit Our Help Center</h6>
                                <p className="font-14 light-color-p">Browse our articles and guides to become a CyberArrow champion.</p></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div className="rightbar-overlay"></div>
        </Fragment>
    );
}

export default Header;
