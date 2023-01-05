
import { useState } from "react";
import {Nav} from "react-bootstrap";
import SidebarLogo from "./SidebarLogo";

const Sidebar = ({categories, customClass}) => {
    const [activeIndex, setActiveIndex] = useState();

    const updateCategoryState = (index) => {
        //close previous opened accordion
            if(typeof(activeIndex) !== "undefined" && activeIndex !== null){
                if(activeIndex !== index){
                    const activeElement = document.getElementById('sectionHeader-'+activeIndex);
                    
                    if(activeElement){
                        activeElement.click();
                    }
                }
            }

            //open new accordion
            setActiveIndex(index);
            
            const el = document.getElementById('sectionHeader-'+index);

            if(activeIndex !== index){
                if(el){
                    el.click();
                }
            }

            setTimeout(() => {
                const anchor = document.querySelector('#sectionHeader-'+index);
                
                if(anchor){
                    anchor.scrollIntoView({
                        behavior: "smooth",
                        block: "center",
                        inline: "nearest"
                    });
                }
            },200);
    }


    return (
        <div className={`${customClass} col-sm-4 card category-card`}>
                        <SidebarLogo/>

                        <Nav variant="pills" className="flex-column">
                            {
                                categories.map((category, index) => {
                                    return (
                                        <Nav.Item key={index.toString()}>
                                            <Nav.Link bsPrefix={`nav-link ${activeIndex === index ? 'active' : ''}`} onClick={() => updateCategoryState(index)}>{category.name}</Nav.Link>
                                        </Nav.Item>
                                    )
                                })
                            }
                        </Nav>
                    </div>
    );
}

export default Sidebar;