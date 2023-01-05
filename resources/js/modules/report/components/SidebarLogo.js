
import { usePage } from "@inertiajs/inertia-react";
import Logo from "./Logo";

const SidebarLogo = () => {
    const { globalSetting, APP_URL, file_driver} = usePage().props;

    return (
        <div className="sidebar-logo">
                            {file_driver === "s3" && <Logo image={globalSetting.company_logo === "assets/images/ebdaa-Logo.png" ? APP_URL + globalSetting.company_logo : globalSetting.company_logo}/>}
                            {file_driver !== "s3" && <Logo image={globalSetting.company_logo === "assets/images/ebdaa-Logo.png" ? APP_URL + globalSetting.company_logo : asset(globalSetting.company_logo)}/>}
                            
                            <h3>{decodeHTMLEntity(globalSetting.display_name)}</h3>
                            <h5 className="text-muted">CONTINUALLY UPDATED</h5>
                        </div>
    );
}

export default SidebarLogo;