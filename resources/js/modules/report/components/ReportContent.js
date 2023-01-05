
import { Accordion} from "react-bootstrap";

const ReportContent = ({categories, globalSetting, widthClass, printable}) => {
    return (
        <div className={`${widthClass}`}>
                        <div className="row row-cols-12 gy-12">
                            <Accordion flush>
                                <div className="report-intro">
                                    <h4 className="mb-1 font-20 clamp clamp-1">CyberArrow report</h4>
                                    <p>
                                        CyberArrow performed a detailed security assessment on {globalSetting.display_name}’s security posture. 
                                        The assessment was performed using globally recognized security standards. 
                                        The assessment included a review of {globalSetting.display_name}’s security policies, procedures, and implemented technical controls.  
                                    </p>
                                    <p>The report includes the following </p>
                                    <ul>
                                        <li>
                                            Assessments performed by CyberArrow on the implemented security controls based on audit checklists that are commonly 
                                            used for performing compliance audits for SOC2, PCI-DSS, ISO/IEC 27001:2022 and other global standards.  
                                        </li>
                                        <li>
                                            Identified non-compliance gaps in security policies, procedures, and IT infrastructure.
                                        </li>
                                    </ul>
                                    <p>
                                        The assessment results are divided in the report under various security domains and sub-domains for better representation. 
                                        The report is representing the current security posture in {globalSetting.display_name} and is continuously updated as and when the processes and 
                                        controls are improved in the organization. 
                                    </p>

                                    <h5 className="mb-1 font-20 clamp clamp-1">How to Benefit from CyberArrow Report</h5>
                                    <p>
                                        This report can be used to enhance the current security posture by closing the identified gaps and weak areas. 
                                        The report can also be shared with management, auditors, and any other interested parties to show {globalSetting.display_name}’s security 
                                        maturity and implemented controls.  ‍‍ 
                                    </p>

                                    <h5 className="mb-1 font-20 clamp clamp-1">How to Evaluate the Results of CyberArrow Assessments</h5>
                                    <p>
                                        {globalSetting.display_name}: Read assessment reports carefully and focus on all the identified weaknesses and vulnerabilities. 
                                        Change policies or configurations in your IT infrastructure to enhance your security posture and mitigate any possible risks.  
                                    </p>
                                    <p>
                                        External Parties: Read assessment reports carefully to understand the current security posture at {globalSetting.display_name}. 
                                    </p>

                                    <h5 className="mb-1 font-20 clamp clamp-1">
                                        CyberArrow’s Assessment Methodology
                                    </h5>
                                    <p>
                                        CyberArrow technical integration feature is used to establish live connections with the company’s IT infrastructure 
                                        to continuously monitor the implemented policies, procedures, and controls. These connections are established to review 
                                        compliance with global and local security standards, regulations, and best practices. The integrations are made with company’s 
                                        endpoint solution, ticketing system, software development tools, infrastructure security tools, and other controls. 
                                    </p>
                                </div>
                                {
                                    categories.map((category, index) => {
                                        return (
                                            <div key={index}>
                                            <Accordion.Item {...(printable ? {} : {eventKey: index.toString()})}>
                                                <Accordion.Header>
                                                    <span className="panel-title" id={`sectionHeader-${index}`}>{category.name} 
                                                    {!category.description && <span className={category.controls_status ? "status-right" : "status-wrong"}><i className={`${category.controls_status ? 'fe-check' : 'fe-x'} fe-2x`}></i></span>}
                                                    </span>
                                                </Accordion.Header>
                                                <Accordion.Body>
                                                    <div className="body-content">
                                                        {category.description ? <p dangerouslySetInnerHTML={{__html: category.description}}/> :       
                                                        <>
                                                        <pre>{category.controls_count} CONTROL TESTS</pre>

                                                        {
                                                            category.controls.map((control) => {
                                                                return (
                                                                    <div key={control.id}>
                                                                        <h5>{control.title}
                                                                            <span className={control.status ? "status-right" : "status-wrong"}>
                                                                                <i className={`${control.status ? "fe-check" : "fe-x"} fe-2x`}/>
                                                                            </span>
                                                                        </h5>
                                                                        {control.status === 1 && control.automation !== 'technical' && 
                                                                        <p>
                                                                            {control.formatted_description}
                                                                        </p>
                                                                        }
                                                                        {control.formatted_alt_description && control.status === 1 && control.automation === 'technical' && <p dangerouslySetInnerHTML={{__html: control.formatted_alt_description}}/>}

                                                                        {control.status === 0 && 
                                                                        <p>
                                                                            {control.formatted_description}
                                                                        </p>
                                                                        }
                                                                        {control.formatted_alt_description && control.status === 0 && <p dangerouslySetInnerHTML={{__html: control.formatted_alt_description}}/>}
                                                                    </div>
                                                                )
                                                            })
                                                        }  
                                                        </> 
                                    }  
                                                    </div>
                                                </Accordion.Body>
                                            </Accordion.Item>
                                            </div>
                                        );
                                    })
                                }
                            </Accordion>
                        </div>
        </div>
    );
}

export default ReportContent;